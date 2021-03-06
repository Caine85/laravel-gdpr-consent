<?php namespace Foothing\Laravel\Consent;

use Foothing\Laravel\Consent\Contracts\ConsentSubject;
use Foothing\Laravel\Consent\Exceptions\NoActiveTreatmentsException;
use Foothing\Laravel\Consent\Exceptions\TreatmentConfigurationException;
use Foothing\Laravel\Consent\Models\Consent;
use Foothing\Laravel\Consent\Models\Event;
use Foothing\Laravel\Consent\Models\Treatment;
use Foothing\Laravel\Consent\Repositories\ConsentRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;

class ConsentApi {

    /**
     * @var Repositories\ConsentRepository
     */
    protected $repository;

    /*public function __construct(ConsentRepository $consents) {
        $this->repository = $consents;
    }*/

    /**
     * Save the consent records for the given subject.
     * Each consent will be related to a given treatment,
     * and each treatment should be obtained from the
     * validate() method.
     *
     * @param array          $treatments
     * @param ConsentSubject $subject
     *
     * @return array
     */
    public function grant(array $treatments, ConsentSubject $subject) {
        $consents = [];

        foreach ($treatments as $treatment) {
            $consent = Consent
                ::whereSubjectId($subject->getSubjectid())
                ->whereTreatmentId($treatment->id)
                ->first();

            if (! $consent) {
                $consent = new Consent();
                $consent->subject_id = $subject->getSubjectId();
                $consent->treatment_id = $treatment->id;
            }

            $consent->save();
            $consents[] = $consent;

            $event = new Event([
                'consent_id' => $consent->id,
                'treatment_id' => $treatment->id,
                'subject_id' => $subject->getSubjectid(),
                'action' => 'consent.grant',
            ]);

            $this->log($event);
        }

        return $consents;
    }

    public function grantAll(ConsentSubject $subject, $includeOptional = false) {
        if (! $treatments = $this->treatments()) {
            throw new \Exception("No active treatments");
        }

        $selected = [];

        foreach($treatments as $treatment) {
            if (! $treatment->required && ! $includeOptional) {
                continue;
            }

            $selected[] = $treatment;
        }

        return $this->grant($selected, $subject);
    }

    public function revoke($treatmentId, ConsentSubject $subject) {
        $consent = Consent::
            whereTreatmentId($treatmentId)
            ->whereSubjectId($subject->getSubjectid())
            ->first();

        $event = new Event([
            'consent_id' => null,
            'treatment_id' => $treatmentId,
            'subject_id' => $subject->getSubjectid(),
            'action' => 'consent.revoke',
        ]);

        $this->log($event);

        if (! $consent) {
            return true;
        }

        \DB::transaction(function() use ($consent)
        {
            $consent->delete();
        });

        return $event;
    }

    public function updateConsents(array $treatments, ConsentSubject $subject) {

    }

protected function removeMeConfig($key) {
        return Config::get($key);
}

    public function revokeConsent(Consent $consent) {
        $consent->action = 'revoke';
        $consent->save();

        return $consent;
    }

    public function erase(ConsentSubject $subject) {
        \DB::transaction(function() use($subject)
        {
            $event = new Event([
                'action' => 'subject.erasure',
                'consent_id' => null,
                'subject_id' => $subject->getSubjectid(),
            ]);

            $this->log($event);

            if ($this->removeMeConfig("gdpr-consent.removeConsentOnErase"))
            {
                Consent::whereSubjectId($subject->getSubjectid())->delete();

                return null;
            }
        });

        return true;
    }

    /**
     * Updates consents.
     *
     * @param array $input
     * The form data. Each item in the array should be like
     * [
     *      'treatmentId1' => 'on',
     *      'treatmentId2' => 'on',
     * ]
     *
     * @param ConsentSubject $subject
     *
     * @param null|string $treatmentName The name of the treatment to update.
     */
    public function update(array $input, ConsentSubject $subject, $treatmentName = null)
    {
        $activeTreatments = $this->treatments($treatmentName);

        foreach ($activeTreatments as $treatment) {
            if ($this->checkFieldIsChecked($input, Config::get('consent.prefix') . $treatment->id))
            {
                $this->grant([$treatment], $subject);
                continue;
            }

            $this->revoke($treatment->id, $subject);
        }
    }

    /**
     * Return the Treatment Collection.
     *
     * @param bool $requiredOnly Whether to return all treatments or only the required ones.
     * @param null|string|array $names The name of the treatment to find.
     * @param bool $returnQuery Whether to return treatments or only the query.
     *
     * @return Collection|Builder|QueryBuilder
     */
    public function treatments($requiredOnly = false, $names = null, $returnQuery = false)
    {
        $query = Treatment::whereActive(true)->orderBy('priority');

        if (is_string($requiredOnly) || is_array($requiredOnly)) $names = $requiredOnly;

        if ($requiredOnly && is_bool($requiredOnly)) {
            $query->whereRequired($requiredOnly);
        }

        if ($names) {
            $names = is_array($names) ? $names : [$names];
            $query->whereIn('name', $names);
        }

        return ($returnQuery ? $query : $query->get());
    }

    /**
     * Return treatments. Meant to be invoked with html form input like
     * 'treatmentId' => 'on'.
     *
     * @param $array
     *
     * @return mixed
     */
    public function getTreatmentsById($array)
    {
        return Treatment::whereIn('id', array_keys($array))->get();
    }

    /**
     * Read treatments from config and updates db accordingly.
     *
     * It will update existing treatments, or create new ones.
     * The config key used to check for existence is the
     * treatment name.
     *
     * @throws TreatmentConfigurationException
     */
    public function configure()
    {
        if (! $treatments = Config::get('gdpr-consent.treatments'))
        {
            throw new TreatmentConfigurationException("Config file is empty or missing.");
        }

        foreach ($treatments as $config)
        {
            if (! $treatment = Treatment::whereName($config['name'])->first())
            {
                $treatment = new Treatment();
            }

            $treatment->forceFill($config);
            $treatment->save();
        }
    }

    /**
     * Input is considered valid when every
     * required treatment is passed and checked (value = 'on').
     *
     * @param array $input
     * The form input as an array. Each consent checkbox must
     * follow the 'PREFIXTREATMENTID' naming convention i.e.
     * [
     *      'treatment_001' => 'on',
     *      'treatment_002' => 'on'
     * ]
     *
     * @param bool|string|array $onlyPresent
     * Whether must be validate only present treatments or not.
     *
     * @param null|string|array $treatmentsName
     * The treatments to be validate, if null all treatments will be validate.
     *
     * @return array|bool
     * The array of validated consents in case of success, false for failure.
     *
     * @throws NoActiveTreatmentsException
     */
    public function validate(array $input, $onlyPresent = false, $treatmentsName = null) {
        $consents = [];

        // If second parameter is not a bool we assume that $onlyPresent is instead $treatmentName parameter
        if (!is_bool($onlyPresent)) {
            $treatmentsName = $onlyPresent;
            $onlyPresent = false;
        }

        // We retrieve all treatments or only specified treatments
        $treatments = ($treatmentsName != null ? $this->treatments($treatmentsName) : Treatment::whereActive(true)->get());

        // No configured treatments.
        if (! $treatments->count()) {
            throw new NoActiveTreatmentsException("No active Treatment");
        }

        // Cycle all treatments and check against input.
        foreach ($treatments as $treatment) {

            if ($onlyPresent && !isset($input[Config::get('consent.prefix') . $treatment->id])) continue;

            if ($this->checkFieldIsInvalid($treatment, $input, Config::get('consent.prefix') . $treatment->id)) {
                return false;
            }

            if ($this->checkFieldIsChecked($input, Config::get('consent.prefix') . $treatment->id)) {
                $consents[] = $treatment;
            }
        }

        return $consents;
    }

    /**
     * Validate a single checkbox. It's considered valid
     * when the treatment is required and the checkbox is
     * not flagged, or when the treatment is optional.
     *
     * @param Treatment $treatment
     * The treatment we need to consent.
     *
     * @param array     $fields
     * The input array.
     *
     * @param string    $fieldName
     * The input field.
     *
     * @return bool
     */
    public function checkFieldIsInvalid(Treatment $treatment, array $fields, $fieldName) {
        return
            $this->checkTreatmentIsRequired($treatment, $fields) &&
            ! $this->checkFieldIsChecked($fields, $fieldName);
    }

    /**
     * Whether a checkbox is checked or not.
     *
     * @param array $fields
     * The input array.
     *
     * @param $fieldName
     * The input field.
     *
     * @return bool
     */
    public function checkFieldIsChecked(array $fields, $fieldName) {
        return
            isset($fields[Config::get('gdpr-consent.prefix') . $fieldName]) &&
            $fields[Config::get('gdpr-consent.prefix') . $fieldName] == 'on';
    }

    /**
     * Whether a treatment is required or not.
     *
     * @param Treatment $treatment
     * The treatment to check.
     *
     * @param array $fields
     * The input array.
     *
     * @return bool
     */
    public function checkTreatmentIsRequired(Treatment $treatment, array $fields) {
        return
            $treatment->required ||
            (isset($treatment->required_with) && $this->array_keys_exists($treatment->required_with, $fields));
    }

    /**
     * Return all subject consents.
     *
     * @param ConsentSubject $subject
     *
     * @return mixed
     */
    public function getSubjectConsents(ConsentSubject $subject)
    {
        return $subject->consents;
    }

    /**
     * Return all subject events.
     *
     * @param ConsentSubject $subject
     *
     * @return mixed
     */
    public function events(ConsentSubject $subject)
    {
        return Event
            ::whereSubjectId($subject->getSubjectid())
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Return true if a treatment exists for a subject,
     * false if it not exists.
     *
     * @param ConsentSubject $subject
     *
     * @param Treatment $treatment
     *
     * @return bool
     */
    public function exists(ConsentSubject $subject, Treatment $treatment)
    {
        return $subject->consents()->whereTreatmentId($treatment->id)->first() != null;
    }

    public function hasSubjectGivenRequiredConsents(ConsentSubject $subject)
    {
        $treatments = $this->treatments(true);

        if (! $treatments->count()) {
            throw new \Exception("bad configuration");
        }

        $consentsCount = Consent
            ::whereSubjectId($subject->getSubjectid())
            ->whereIn('treatment_id', $treatments->pluck('id'))
            ->count();

        return $consentsCount >= $treatments->count();
    }

    public function log($event)
    {
        $event->ip = Request::ip();

        $event->payload = Request::except([
            'password',
            'password_confirmation',
            '_token',
        ]);

        $event->save();

        return $event;
    }

    private function array_keys_exists(array $keys, array $arr) {
        return !array_diff_key(array_flip($keys), $arr);
    }
}

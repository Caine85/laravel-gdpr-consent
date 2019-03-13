<?php namespace Foothing\Laravel\Consent\Models;

use Foothing\Laravel\Consent\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class Treatment extends Model {

    use Uuids;

    protected $table = 'gdpr_treatment';

    public $incrementing = false;

    /**
     * Get the array of input names where the Treatment is required
     *
     * @param $value
     * @return array
     */
    public function getRequiredWithAttribute($value) {
        return json_decode($value, true);
    }

    /**
     * Set the array of input names where the Treatment is required
     *
     * @param $value
     */
    public function setRequiredWithAttribute($value) {
        $this->attributes['required_with'] = json_encode($value);
    }
}
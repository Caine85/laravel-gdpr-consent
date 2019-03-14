<?php namespace Foothing\Laravel\Consent\Traits;

use Foothing\Laravel\Consent\Models\Consent;
use Foothing\Laravel\Consent\Models\Event;

trait HasConsents {

    public function consents()
    {
        return $this->hasMany(Consent::class, 'subject_id');
    }

    public function consentsEvents()
    {
        return $this->hasMany(Event::class, 'subject_id');
    }

}

<?php namespace Foothing\Laravel\Consent\Models;

use Foothing\Laravel\Consent\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Event extends Model
{
    use Uuids;

    protected $table = 'gdpr_event';

    public $fillable = ['id', 'action', 'consent_id', 'treatment_id', 'subject_id', 'payload'];

    public function consent()
    {
        return $this->belongsTo(Consent::class);
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function getPayloadAttribute($value) {
        return decrypt($value);
    }

    public function setPayloadAttribute($value)
    {
        $this->attributes['payload'] = Crypt::encrypt(json_encode($value));
    }

    public function setIpAttribute($value)
    {
        $this->attributes['ip'] = Crypt::encrypt($value);
    }
}

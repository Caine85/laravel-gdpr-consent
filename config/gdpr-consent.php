<?php
return [

    // Prefix applied to a string to indicate
    // that you are acting on a consent.
    // It is necessary to apply this prefix to the
    // name of the consents in the inputs of a form.
    'prefix' => 'consent_',

    // You can choose if consent is deleted or not
    // after ConsentSubject deletion
    'removeConsentOnErase' => true,

    'treatments' => [
        [
            // A logical name for your treatment.
            'name' => 'gdpr.privacy',

            // You can specify which document
            // describes the treatment type
            // with a document version and url.
            // This part is optional.
            'documentVersion' => '1.0',
            'documentUrl' => env('PRIVACY_POLICY'),

            // Whether this treatment is active or not.
            // The reason why this flag is here is to
            // allow for progressive modifications, so you
            // can keep track of what the end user gave
            // consent to. So if you are upgrading or
            // changing the treatment the recommended
            // process is to deactivate the current one
            // then add a new record.
            'active' => true,

            // Set if this treatment is mandatory or optional.
            'required' => true,

            // Set if this treatment is required when an input
            // name is present.
            // Must be unset or an array.
            // This part is optional.
            'required_with' => [
                'first-name'
            ],

            // A description text to be shown near a checkbox
            // or anywhere in your UI.
            'description' => 'gdpr.privacy.text',

            // Set what should be
            // listed first in UI.
            'priority' => 0,
        ],
        [
            'name' => 'gdpr.marketing',
            'documentVersion' => '1.0',
            'documentUrl' => env('PRIVACY_POLICY'),
            'active' => true,
            'required' => false,
            'description' => 'gdpr.marketing.text',
            'priority' => 1,
        ],
    ]

];
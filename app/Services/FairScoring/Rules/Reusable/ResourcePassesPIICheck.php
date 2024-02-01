<?php

namespace App\Services\FairScoring\Rules\Reusable;

use App\Services\FairScoring\Interfaces\FairScoreRule;
use App\Services\FairScoring\Rules\BaseRule;

class ResourcePassesPIICheck extends BaseRule implements FairScoreRule
{
    public static $metadataCondition = 'RESOURCE complies with basic Personal Information Protection principles';
    public static $scoring = '2 points';
    public static $recommendation = 'Verify that you have taken all the actions needed to tackle the existence of PII issues';
    public static $anchor = 'resource-files';

    public static function calculateScore($metadataRecord)
    {
        return self::meetsCondition($metadataRecord) ? 2 : 0;
    }

    public static function meetsCondition($metadataRecord)
    {
        $files = data_get($metadataRecord, 'resource_files');

        if (empty($files)) {
            return false;
        }

        foreach ($files as $file) {
            if (empty($file['pii_terms_accepted_at'])) {
                return false;
            }
        }

        return true;
    }
}

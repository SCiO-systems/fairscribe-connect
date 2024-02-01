<?php

namespace App\Services\FairScoring\Rules\Interoperable;

use App\Services\FairScoring\Interfaces\FairScoreRule;
use App\Services\FairScoring\Rules\BaseRule;

class DatasetHasAnnotatedData extends BaseRule implements FairScoreRule
{
    public static $metadataCondition = 'The data included in the DATASET are annotated';
    public static $scoring = '2.5 points';
    public static $recommendation = 'Produce an annotated version of the described dataset';
    public static $anchor = 'resource-files';

    public static function calculateScore($metadataRecord)
    {
        return self::meetsCondition($metadataRecord) ? 2.5 : 0;
    }

    public static function meetsCondition($metadataRecord)
    {
        $files = data_get($metadataRecord, 'resource_files');

        if (empty($files)) {
            return false;
        }

        foreach ($files as $file) {
            $filename = $file['filename'];
            if (strpos(strtolower($filename), '.vmpr') !== false) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Utilities\Repositories\Mappers;

use Exception;

class DataverseMapper
{

    protected $metadata;

    public function __construct($metadata = null)
    {
        $this->metadata = $metadata;
    }

    public function getSchema($json = false)
    {
        $citation_fields = [];
        $geospatial_fields = [];
        $social_science_fields = [];

        $citation_fields[] = $this->getTitle();
        $citation_fields[] = $this->getDescriptions();
        $citation_fields[] = $this->getSubject();
        $citation_fields[] = $this->getAuthors();
        $citation_fields[] = $this->getContactPoints();
        $citation_fields[] = $this->getTimePeriodCovered();
        $citation_fields[] = $this->getDateOfCollection();
        $citation_fields[] = $this->getLanguage();
        $citation_fields[] = $this->getKeywords();
        $citation_fields[] = $this->getContributors();
        $citation_fields[] = $this->getGrantNumbers();
        $citation_fields[] = $this->getPublications();

        $geospatial_fields[] = $this->getGeographicCoverage();

        $social_science_fields[] = $this->getUnitsOfAnalysis();
        $social_science_fields[] = $this->getUniverse();
        $social_science_fields[] = $this->getFrequencyOfDataCollection();
        $social_science_fields[] = $this->getCollectionMode();
        $social_science_fields[] = $this->getResearchInstrument();
        $social_science_fields[] = $this->getSamplingProcedure();

        $citation_fields = array_values(array_filter(
            $citation_fields,
            function ($field) {
                return !is_null($field);
            }
        ));

        $geospatial_fields = array_values(array_filter(
            $geospatial_fields,
            function ($field) {
                return !is_null($field);
            }
        ));

        $social_science_fields = array_values(array_filter(
            $social_science_fields,
            function ($field) {
                return !is_null($field);
            }
        ));

        if ($this->isLicenseCC0()) {
            $license = 'CC0';
            $termsOfUse = 'CC0 Waiver';
        }

        if ($this->getTermsOfUse()) {
            $license = 'No waiver';
            $termsOfUse = $this->getTermsOfUse();
        }

        if (!$this->getTermsOfUse() && !$this->isLicenseCC0()) {
            $license = 'No waiver';
            $termsOfUse = $this->getLicense();
        }

        $schema = [
            'datasetVersion' => [
                'license' =>  $license,
                'termsOfUse' => $termsOfUse,
                'metadataBlocks' => [
                    'citation' => [
                        'displayName' => 'Citation Metadata',
                        'fields' => $citation_fields,
                    ],
                ]
            ],
        ];

        if (!empty($geospatial_fields)) {
            $schema['datasetVersion']['metadataBlocks']['geospatial'] = [
                'displayName' => 'Geospatial Metadata',
                'fields' => $geospatial_fields,
            ];
        }

        if (!empty($social_science_fields)) {
            $schema['datasetVersion']['metadataBlocks']['socialscience'] = [
                'displayName' => 'Social Science and Humanities Metadata',
                'fields' => $social_science_fields,
            ];
        }

        return $json ? json_encode($schema) : $schema;
    }

    public function getSchemaForUpdate($json = false)
    {
        $schema = $this->getSchema(false)['datasetVersion'];

        return $json ? json_encode($schema) : $schema;
    }

    public function validate()
    {
        $this->checkTitles();
        $this->checkDescriptions();
        $this->checkAuthors();
        $this->checkContactPoints();
    }

    protected function checkTitles()
    {
        $titles = data_get($this->metadata, 'title.0.value');
        if (empty($titles)) {
            throw new Exception('No titles found in metadata.');
        }
    }

    protected function checkDescriptions()
    {
        $descriptions = data_get($this->metadata, 'description.0.value');
        if (empty($descriptions)) {
            throw new Exception('No descriptions found in metadata.');
        }
    }

    protected function checkAuthors()
    {
        $authors = data_get($this->metadata, 'authors');
        if (empty($authors)) {
            throw new Exception('No authors found in metadata.');
        }
    }

    protected function checkContactPoints()
    {
        $authors = data_get($this->metadata, 'contact_points');
        if (empty($authors)) {
            throw new Exception('No contact points found in metadata.');
        }
    }

    protected function getSubject()
    {
        return [
            "typeName" => "subject",
            "multiple" => true,
            "typeClass" => "controlledVocabulary",
            "value" => ["Agricultural Sciences"]
        ];
    }

    protected function getDescriptions()
    {
        $descriptions = data_get($this->metadata, 'description');

        $descriptionsArray = [
            'typeName' => 'dsDescription',
            'multiple' => true,
            'typeClass' => 'compound',
        ];

        foreach ($descriptions as $ds) {
            $descriptionsArray['value'][] = [
                'dsDescriptionValue' => [
                    'typeName' => 'dsDescriptionValue',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $ds['value']
                ],
                'dsDescriptionDate' => [
                    'typeName' => 'dsDescriptionDate',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => date('Y-m-d')
                ],
            ];
        }

        return $descriptionsArray;
    }

    protected function getLicense()
    {
        return data_get($this->metadata, 'rights.license');
    }

    protected function isLicenseCC0()
    {
        return $this->getLicense() == 'CC0 1.0';
    }

    protected function getTermsOfUse()
    {
        return data_get($this->metadata, 'rights.terms_of_use.0.value');
    }

    protected function getTitle()
    {
        return [
            "typeName" => "title",
            "multiple" => false,
            "typeClass" => "primitive",
            "value" => data_get($this->metadata, 'title.0.value')
        ];
    }

    protected function getAuthors()
    {
        $authors = data_get($this->metadata, 'authors');

        $authorsArray = [
            'typeName' => 'author',
            'multiple' => true,
            'typeClass' => 'compound',
        ];

        foreach ($authors as $author) {
            $values = [];

            $identifier = data_get($author, 'agent_ids.0.value');
            if (!empty($identifier)) {
                $values['authorIdentifier'] = [
                    'typeName' => 'authorIdentifier',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $identifier
                ];
            }

            $scheme = data_get($author, 'agent_ids.0.schema');
            if (!empty($scheme)) {
                $values['authorIdentifierScheme'] = [
                    'typeName' => 'authorIdentifierScheme',
                    'multiple' => false,
                    'typeClass' => 'controlledVocabulary',
                    'value' => $scheme,
                ];
            }

            $name = data_get($author, 'full_name');
            if (!empty($name)) {
                $values['authorName'] = [
                    'typeName' => 'authorName',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' =>  $name,
                ];
            }

            $authorsArray['value'][] = $values;
        }

        return $authorsArray;
    }

    protected function getContactPoints()
    {
        $contactPoints = data_get($this->metadata, 'contact_points');

        $contactPointsArray = [
            'typeName' => 'datasetContact',
            'multiple' => true,
            'typeClass' => 'compound',
        ];

        foreach ($contactPoints as $cp) {
            $values = [
                'datasetContactName' => [
                    'typeName' => 'datasetContactName',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $cp['full_name']
                ],
                'datasetContactEmail' => [
                    'typeName' => 'datasetContactEmail',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $cp['email']
                ]
            ];

            $contactPointsArray['value'][] = $values;
        }

        return $contactPointsArray;
    }

    protected function getTimePeriodCovered()
    {
        $from = data_get($this->metadata, "data_temporal_coverage.from");
        $to = data_get($this->metadata, "data_temporal_coverage.to");

        if (empty($from) || empty($to)) return null;

        return [
            "typeName" => "timePeriodCovered",
            "multiple" => true,
            "typeClass" => "compound",
            "value" => [
                [
                    "timePeriodCoveredStart" =>  [
                        "typeName" =>  "timePeriodCoveredStart",
                        "multiple" =>  false,
                        "typeClass" =>  "primitive",
                        "value" =>  data_get($this->metadata, "data_temporal_coverage.from")
                    ],
                    "timePeriodCoveredEnd" =>  [
                        "typeName" =>  "timePeriodCoveredEnd",
                        "multiple" =>  false,
                        "typeClass" =>  "primitive",
                        "value" =>  data_get($this->metadata, "data_temporal_coverage.to")
                    ],
                ]
            ],
        ];
    }

    protected function getDateOfCollection()
    {
        $from = data_get($this->metadata, "data_collection_period.from");
        $to = data_get($this->metadata, "data_collection_period.to");

        if (empty($from) || empty($to)) return null;

        return [
            "typeName" => "dateOfCollection",
            "multiple" => true,
            "typeClass" => "compound",
            "value" => [
                [
                    "dateOfCollectionStart" =>  [
                        "typeName" =>  "dateOfCollectionStart",
                        "multiple" =>  false,
                        "typeClass" =>  "primitive",
                        "value" =>  data_get($this->metadata, "data_collection_period.from")
                    ],
                    "dateOfCollectionEnd" =>  [
                        "typeName" =>  "dateOfCollectionEnd",
                        "multiple" =>  false,
                        "typeClass" =>  "primitive",
                        "value" =>  data_get($this->metadata, "data_collection_period.to")
                    ],
                ]
            ],
        ];
    }

    protected function getLanguage()
    {
        if (empty(data_get($this->metadata, "resource_language"))) return null;

        return [
            "typeName" => "language",
            "multiple" => true,
            "typeClass" => "controlledVocabulary",
            "value" => [data_get($this->metadata, 'resource_language.name')]
        ];
    }

    protected function getKeywords()
    {
        $keywords = data_get($this->metadata, 'keywords');
        if (empty($keywords)) return null;

        $keywordsArray = [
            'typeName' => 'keyword',
            'multiple' => true,
            'typeClass' => 'compound',
        ];

        foreach ($keywords as $kw) {
            $values = [
                'keywordVocabulary' => [
                    'typeName' => 'keywordVocabulary',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $kw['scheme']
                ],
                'keywordValue' => [
                    'typeName' => 'keywordValue',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $kw['value']
                ],
            ];

            $keywordsArray['value'][] = $values;
        }

        return $keywordsArray;
    }

    protected function getContributors()
    {
        $funding_orgs = data_get($this->metadata, 'funding_organisations');

        if (empty($funding_orgs)) return null;

        $contributorsArray = [
            'typeName' => 'contributor',
            'multiple' => true,
            'typeClass' => 'compound',
        ];

        foreach ($funding_orgs as $fo) {
            if (empty($fo['full_name'])) {
                continue;
            }

            $values = [
                'contributorName' => [
                    'typeName' => 'contributorName',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $fo['full_name']
                ],
                'contributorType' => [
                    'typeName' => 'contributorType',
                    'multiple' => false,
                    'typeClass' => 'controlledVocabulary',
                    'value' => 'Funder'
                ],
            ];

            $contributorsArray['value'][] = $values;
        }

        return empty($contributorsArray['value']) ? null : $contributorsArray;
    }

    protected function getGrantNumbers()
    {
        $projects = data_get($this->metadata, 'projects');

        if (empty($projects)) return null;

        $grantNumbersArray = [
            'typeName' => 'grantNumber',
            'multiple' => true,
            'typeClass' => 'compound',
        ];

        foreach ($projects as $p) {
            $values = [
                'grantNumberAgency' => [
                    'typeName' => 'grantNumberAgency',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $p['full_name']
                ],
            ];

            $grantNumbersArray['value'][] = $values;
        }

        return $grantNumbersArray;
    }

    protected function getPublications()
    {
        $related_resources = data_get($this->metadata, 'related_resources');
        if (empty($related_resources)) return null;

        $publicationsArray = [
            'typeName' => 'publication',
            'multiple' => true,
            'typeClass' => 'compound',
        ];

        foreach ($related_resources as $rr) {
            $values = [
                'publicationIDType' => [
                    'typeName' => 'publicationIDType',
                    'multiple' => false,
                    'typeClass' => 'controlledVocabulary',
                    'value' => 'doi'
                ],
                'publicationIDNumber' => [
                    'typeName' => 'publicationIDNumber',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $rr['DOI']
                ],
                'publicationURL' => [
                    'typeName' => 'publicationURL',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => 'https://doi.org/' . $rr['DOI'],
                ],
            ];

            $publicationsArray['value'][] = $values;
        }

        return $publicationsArray;
    }

    protected function getGeographicCoverage()
    {
        $geography_countries = data_get($this->metadata, 'geography.countries');
        $geography_regions = data_get($this->metadata, 'geography.regions');
        if (empty($geography_countries) && empty($geography_regions)) return null;

        $geoCoverageArray = [
            'typeName' => 'geographicCoverage',
            'multiple' => true,
            'typeClass' => 'compound',
        ];

        if (!empty($geography_countries)) {
            foreach ($geography_countries as $gc) {
                $values = [
                    'country' => [
                        'typeName' => 'country',
                        'multiple' => false,
                        'typeClass' => 'controlledVocabulary',
                        'value' => $gc['value']
                    ],
                ];

                $geoCoverageArray['value'][] = $values;
            }
        }

        if (!empty($geography_regions)) {
            foreach ($geography_regions as $gr) {
                $values = [
                    'otherGeographicCoverage' => [
                        'typeName' => 'otherGeographicCoverage',
                        'multiple' => false,
                        'typeClass' => 'primitive',
                        'value' => $gr['value']
                    ],
                ];

                $geoCoverageArray['value'][] = $values;
            }
        }

        return $geoCoverageArray;
    }

    protected function getUnitsOfAnalysis()
    {
        $units = data_get($this->metadata, 'methodology.unit_of_analysis');
        if (empty($units)) return null;

        $unitOfAnalysisArray = [
            'typeName' => 'unitOfAnalysis',
            'multiple' => true,
            'typeClass' => 'primitive',
            'value' => array_map(function ($u) {
                return $u['value'];
            }, $units),
        ];

        return $unitOfAnalysisArray;
    }

    protected function getUniverse()
    {
        $universe = data_get($this->metadata, 'methodology.universe');
        if (empty($universe)) return null;

        $universeArray = [
            'typeName' => 'universe',
            'multiple' => true,
            'typeClass' => 'primitive',
            'value' => array_map(function ($u) {
                return $u['value'];
            }, $universe),
        ];

        return $universeArray;
    }

    protected function getFrequencyOfDataCollection()
    {
        $dcf = data_get($this->metadata, 'methodology.data_collection_frequency');
        if (empty($dcf)) return null;

        return [
            "typeName" => "frequencyOfDataCollection",
            "multiple" => false,
            "typeClass" => "primitive",
            "value" => $dcf,
        ];
    }

    protected function getCollectionMode()
    {
        $dcm = data_get($this->metadata, 'methodology.data_collection_mode');
        if (empty($dcm)) return null;

        return [
            "typeName" => "collectionMode",
            "multiple" => false,
            "typeClass" => "primitive",
            "value" => $dcm,
        ];
    }

    protected function getResearchInstrument()
    {
        $ins = data_get($this->metadata, 'methodology.instrument');
        if (empty($ins)) return null;

        return [
            "typeName" => "researchInstrument",
            "multiple" => false,
            "typeClass" => "primitive",
            "value" => $ins,
        ];
    }

    protected function getSamplingProcedure()
    {
        $sp = data_get($this->metadata, 'methodology.sampling_process');
        if (empty($sp)) return null;

        return [
            "typeName" => "samplingProcedure",
            "multiple" => false,
            "typeClass" => "primitive",
            "value" => $sp,
        ];
    }
}

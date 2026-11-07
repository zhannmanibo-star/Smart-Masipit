<?php

namespace App\Support;

use App\Models\DocumentRequest;
use App\Models\Resident;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CertificateDetails
{
    public const CIVIL_STATUSES = [
        'Single',
        'Married',
        'Widowed',
        'Separated',
        'Annulled',
    ];

    public const CODES = [
        'barangay_clearance' => 'CLR',
        'certificate_of_indigency' => 'IND',
        'certificate_of_residency' => 'RES',
        'oath_of_undertaking' => 'OATH',
        'first_time_jobseeker_certification' => 'FTJ',
        'certificate_of_closure' => 'CLO',
        'certificate_of_good_moral' => 'GM',
        'barangay_occupancy_permit' => 'OCC',
    ];

    public const FIELDS = [
        'civil_status' => [
            'label' => 'Civil status',
            'type' => 'select',
            'options' => self::CIVIL_STATUSES,
        ],
        'occupation' => [
            'label' => 'Occupation / current status',
            'type' => 'text',
            'max' => 150,
        ],
        'residency_since' => [
            'label' => 'Continuous residency start date',
            'type' => 'date',
        ],
        'purok' => [
            'label' => 'Purok / locality within Barangay Masipit',
            'type' => 'text',
            'max' => 150,
        ],
        'first_time_jobseeker' => [
            'label' =>
                'The applicant declares that this is their first time '
                . 'actively seeking employment and that they have not '
                . 'previously availed of the first-time jobseeker benefit.',
            'type' => 'checkbox',
        ],
        'business_name' => [
            'label' => 'Registered business name',
            'type' => 'text',
            'max' => 200,
        ],
        'owner_name' => [
            'label' => 'Business owner’s full name',
            'type' => 'text',
            'max' => 150,
        ],
        'business_address' => [
            'label' => 'Business address within Barangay Masipit',
            'type' => 'text',
            'max' => 250,
        ],
        'closure_date' => [
            'label' => 'Effective closure date',
            'type' => 'date',
        ],
        'filipino_citizen' => [
            'label' => 'The applicant declares that they are a Filipino citizen.',
            'type' => 'checkbox',
        ],
        'property_address' => [
            'label' => 'Property address within Barangay Masipit',
            'type' => 'text',
            'max' => 250,
        ],
    ];

    public const TYPE_FIELDS = [
        'barangay_clearance' => ['civil_status', 'occupation'],
        'certificate_of_indigency' => [],
        'certificate_of_residency' => ['residency_since'],
        'oath_of_undertaking' => [
            'residency_since',
            'purok',
            'first_time_jobseeker',
        ],
        'first_time_jobseeker_certification' => [
            'residency_since',
            'purok',
            'first_time_jobseeker',
        ],
        'certificate_of_closure' => [
            'business_name',
            'owner_name',
            'business_address',
            'closure_date',
        ],
        'certificate_of_good_moral' => [
            'civil_status',
            'filipino_citizen',
        ],
        'barangay_occupancy_permit' => ['property_address'],
    ];

    public const CHECKS = [
        'barangay_clearance' =>
            'I verified the submitted details and completed the required '
            . 'Lupon record check. The clearance statement is supported '
            . 'by the records reviewed.',

        'certificate_of_indigency' =>
            'I verified the applicant’s indigency eligibility and the '
            . 'supporting information for the certification.',

        'certificate_of_residency' =>
            'I verified the applicant’s continuous residency and '
            . 'the submitted residency start date.',

        'oath_of_undertaking' =>
            'I reviewed the applicant’s first-time jobseeker declarations '
            . 'and residency information. The printed oath still requires '
            . 'the applicant’s signature and the required witnesses.',

        'first_time_jobseeker_certification' =>
            'I checked first-time jobseeker eligibility, the required '
            . 'residency period, the barangay roster, and previous '
            . 'availment records. I verified that the required oath '
            . 'was signed before the appropriate barangay officials.',

        'certificate_of_closure' =>
            'I verified the business identity, owner, location, '
            . 'and effective closure date against supporting records.',

        'certificate_of_good_moral' =>
            'I verified the applicant’s identity, citizenship and civil '
            . 'status, and completed the record checks needed to support '
            . 'the good-moral and no-derogatory-record statements. '
            . 'The designated signatory has reviewed their basis.',

        'barangay_occupancy_permit' =>
            'I verified the applicant and property, and confirmed that '
            . 'the necessary barangay review supports the permission '
            . 'and no-objection statements in this template.',
    ];

    public static function fields(string $type): array
    {
        return array_intersect_key(
            self::FIELDS,
            array_flip(self::TYPE_FIELDS[$type] ?? [])
        );
    }

    public static function validate(
        string $type,
        mixed $input,
        string $birthDate,
        string $key = 'details'
    ): array {
        if (! array_key_exists($type, DocumentRequest::TYPES)) {
            throw ValidationException::withMessages([
                'document_type' => 'Select a supported document type.',
            ]);
        }

        $input ??= [];

        if (is_array($input)) {
            foreach ($input as $field => $value) {
                if (is_string($value)) {
                    $input[$field] = trim($value);
                }
            }
        }

        $fields = self::fields($type);
        $allowed = array_keys($fields);

        $rules = [
            $key => [
                'present',
                $allowed ? 'array:' . implode(',', $allowed) : 'array',
            ],
        ];

        $labels = [];

        foreach ($fields as $field => $definition) {
            $path = $key . '.' . $field;
            $labels[$path] = $definition['label'];

            $rules[$path] = match ($definition['type']) {
                'select' => [
                    'required',
                    'string',
                    Rule::in($definition['options']),
                ],
                'checkbox' => ['accepted'],
                'date' => [
                    'required',
                    'date_format:Y-m-d',
                    'before_or_equal:' . now('Asia/Manila')->toDateString(),
                ],
                default => [
                    'required',
                    'string',
                    'max:' . $definition['max'],
                ],
            };
        }

        if (isset($fields['residency_since'])) {
            $rules[$key . '.residency_since'][] = 'after_or_equal:' . $birthDate;
        }

        $validator = Validator::make(
            [$key => $input],
            $rules,
            [],
            $labels
        );

        $validator->after(function ($validator) use (
            $input,
            $allowed,
            $key,
            $type,
            $birthDate
        ) {
            if (is_array($input) && array_diff(array_keys($input), $allowed)) {
                $validator->errors()->add(
                    $key,
                    'Unexpected certificate fields were submitted.'
                );
            }

            if ($type === 'certificate_of_good_moral') {
                $birth = \DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
                $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila'));

                if (
                    ! $birth
                    || $birth->format('Y-m-d') !== $birthDate
                    || $birth > $today
                    || $birth->diff($today)->y < 18
                ) {
                    $validator->errors()->add(
                        $key,
                        'The supplied Good Moral template states “of legal age”. '
                        . 'A different barangay-approved template is needed '
                        . 'for an applicant under 18.'
                    );
                }
            }
        });

        $validated = $validator->validate()[$key];

        $result = [];

        foreach ($fields as $field => $definition) {
            $result[$field] = $definition['type'] === 'checkbox'
                ? true
                : $validated[$field];
        }

        return $result;
    }

    public static function profile(Resident $resident): array
    {
        return [
            'resident_number' => $resident->resident_number,
            'first_name' => $resident->first_name,
            'middle_name' => $resident->middle_name,
            'last_name' => $resident->last_name,
            'birth_date' => $resident->birth_date->format('Y-m-d'),
            'sex' => $resident->sex,
            'sitio' => $resident->sitio,
            'address' => $resident->address,
        ];
    }
}
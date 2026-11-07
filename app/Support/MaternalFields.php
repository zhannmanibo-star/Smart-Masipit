<?php

namespace App\Support;

class MaternalFields
{
    public const RECORD = [
        'Record information' => [
            'clinic' => ['Clinic', 'text'],
            'clinic_number' => ['Clinic record number, if assigned', 'text'],
            'philhealth_number' => ['PhilHealth number', 'text'],
            'occupation' => ['Patient occupation', 'text'],
            'husband_name' => ["Husband's name, if applicable", 'text'],
            'husband_occupation' => ["Husband's occupation", 'text'],
        ],

        'Present pregnancy' => [
            'gravida' => ['Gravida', 'count'],
            'para' => ['Para', 'count'],
            'f' => ['F — as recorded on source form', 'count'],
            'p' => ['P — as recorded on source form', 'count'],
            'a' => ['A — as recorded on source form', 'count'],
            'l' => ['L — as recorded on source form', 'count'],
            'fetal_death' => ['Fetal death — number recorded', 'count'],
            'cause_of_death' => ['Cause of fetal death, if recorded', 'text'],
            'lmp' => ['First day of last menstrual period', 'date'],
            'edc' => ['Expected date of confinement', 'date'],
            'tetanus_status' => ['Tetanus toxoid status — specify', 'text'],
        ],

        'Past medical history and family history' => [
            'medical_history' => ['Past medical history: any disease / illness?', 'choice'],
            'medical_history_details' => ['Past medical history — specify', 'text'],
            'family_history' => ['Family history: any disease / illness?', 'choice'],
            'family_history_details' => ['Family history — specify', 'text'],
        ],

        'Previous pregnancies complicated with' => [
            'hemorrhage' => ['Hemorrhage', 'choice'],
            'toxemia' => ['Toxemia', 'choice'],
            'placenta_previa' => ['Placenta previa', 'choice'],
            'sepsis' => ['Sepsis', 'choice'],
            'previous_other' => ['Other previous pregnancy complications — specify', 'text'],
        ],

        'Present illness' => [
            'nausea' => ['Nausea', 'choice'],
            'vomiting' => ['Vomiting', 'choice'],
            'headache' => ['Headache', 'choice'],
            'dizziness' => ['Dizziness', 'choice'],
            'edema' => ['Edema', 'choice'],
            'cramps' => ['Cramps', 'choice'],
            'pruritus' => ['Pruritus', 'choice'],
            'bleeding' => ['Bleeding', 'choice'],
            'illness_other' => ['Other present illness — specify', 'text'],
        ],

        'Family planning' => [
            'family_planning' => ['Has family planning been practiced?', 'choice'],
            'family_planning_methods' => ['If yes, what method/s?', 'text'],
            'willing_to_practice' => ['If no, willing to practice?', 'choice'],
        ],
    ];

    public const VISIT = [
        'Antepartum visit' => [
            'vital_signs' => ['Vital signs, including BP with units', 'text'],
            'height' => ['HT — include unit, for example cm', 'text'],
            'weight' => ['WT — include unit, for example kg', 'text'],
            'bmi' => ['BMI — as assessed / recorded', 'text'],
            'aog' => ['AOG — include weeks / days', 'text'],
            'fetal_presentation' => ['Fetal presentation', 'text'],
            'fundic_height' => ['Fundic height — include unit', 'text'],
            'fht' => ['FHT — include unit', 'text'],
            'remarks' => ['Remarks — noted signs and symptoms', 'text'],
        ],
    ];

    public static function rules(array $sections): array
    {
        $rules = [];

        foreach ($sections as $fields) {
            foreach ($fields as $key => [$label, $type]) {
                $rules[$key] = match ($type) {
                    'count' => ['nullable', 'integer', 'min:0', 'max:99'],
                    'date' => ['nullable', 'date_format:Y-m-d'],
                    'choice' => ['nullable', 'in:yes,no'],
                    default => ['nullable', 'string', 'max:250'],
                };
            }
        }

        return $rules;
    }

    public static function labels(array $sections): array
    {
        $labels = [];

        foreach ($sections as $fields) {
            foreach ($fields as $key => [$label, $type]) {
                $labels[$key] = $label;
            }
        }

        return $labels;
    }
}
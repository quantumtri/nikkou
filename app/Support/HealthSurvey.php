<?php

namespace App\Support;

/** The "Survei Kondisi Kesehatan" checklist (page 2 of the printed form). Keys are stored; labels are shown. */
class HealthSurvey
{
    /** Group key => [label, items, whether the group has a "Lainnya" free-text line]. */
    public const GROUPS = [
        'symptoms' => ['I. Gejala saat ini', [
            'sleep_difficulty' => 'Sulit tidur', 'high_stress' => 'Stres tinggi', 'poor_sleep_quality' => 'Kualitas tidur buruk',
            'low_stamina' => 'Daya tahan tubuh rendah', 'easily_tired' => 'Mudah lelah', 'chronic_pain' => 'Nyeri kronis',
        ], false],
        'cardiovascular' => ['II. Sistem kardiovaskular', [
            'high_bp' => 'Tekanan darah tinggi', 'low_bp' => 'Tekanan darah rendah', 'high_cholesterol' => 'Lemak darah tinggi',
            'coronary' => 'Penyakit jantung koroner', 'angina' => 'Angina (nyeri dada jantung)', 'arrhythmia' => 'Aritmia (detak tidak teratur)',
            'heart_attack' => 'Serangan jantung', 'stroke' => 'Stroke',
        ], true],
        'metabolic' => ['III. Metabolisme dan endokrin', [
            'diabetes' => 'Diabetes', 'obesity' => 'Obesitas', 'gout' => 'Asam urat', 'thyroid' => 'Penyakit tiroid', 'metabolic_syndrome' => 'Sindrom metabolik',
        ], true],
        'respiratory' => ['IV. Sistem pernapasan', [
            'asthma' => 'Asma', 'bronchitis' => 'Bronkitis kronis', 'copd' => 'PPOK (COPD)', 'lung_fibrosis' => 'Fibrosis paru',
            'sleep_apnea' => 'Henti napas saat tidur (sleep apnea)',
        ], true],
        'digestive' => ['V. Lambung, usus, dan pencernaan', [
            'reflux' => 'Refluks asam lambung', 'gastric_ulcer' => 'Tukak lambung', 'duodenal_ulcer' => 'Tukak usus dua belas jari',
            'ibs' => 'Sindrom iritasi usus', 'hepatitis' => 'Hepatitis', 'fatty_liver' => 'Perlemakan hati',
        ], true],
        'nervous' => ['VI. Sistem saraf', [
            'migraine' => 'Migrain', 'insomnia' => 'Insomnia', 'depression' => 'Depresi', 'anxiety' => 'Gangguan kecemasan',
            'parkinson' => 'Parkinson', 'alzheimer' => 'Alzheimer / demensia',
        ], true],
        'musculoskeletal' => ['VII. Tulang dan otot', [
            'arthritis' => 'Radang sendi', 'osteoarthritis' => 'Pengapuran sendi', 'osteoporosis' => 'Osteoporosis',
            'pinched_nerve' => 'Saraf kejepit / hernia bantalan tulang belakang', 'chronic_muscle_pain' => 'Nyeri otot kronis',
        ], true],
        'immune' => ['VIII. Imun dan alergi', [
            'allergic_rhinitis' => 'Rinitis alergi', 'atopic_dermatitis' => 'Alergi kulit / dermatitis atopik',
            'autoimmune' => 'Penyakit autoimun', 'weather_food_allergy' => 'Alergi cuaca atau makanan',
        ], true],
        'tumor' => ['IX. Tumor / kanker', [
            'none' => 'Tidak ada', 'had_cancer' => 'Pernah menderita kanker',
        ], false],
        'other_health' => ['X. Kondisi kesehatan lain', [
            'long_term_meds' => 'Minum obat jangka panjang', 'smoker' => 'Merokok jangka panjang',
            'long_term_alcohol' => 'Minum alkohol jangka panjang', 'chronic_fatigue' => 'Kelelahan kronis',
        ], true],
    ];

    /** @return array<string, string> group key => label */
    public static function groupLabels(): array
    {
        return array_map(fn ($g) => $g[0], self::GROUPS);
    }

    /** @return array<string, string> item key => label for one group */
    public static function items(string $group): array
    {
        return self::GROUPS[$group][1];
    }

    public static function hasOther(string $group): bool
    {
        return self::GROUPS[$group][2];
    }

    /**
     * Readable lines of what was ticked, one per group, skipping empty groups.
     *
     * @param  array<string, list<string>>  $conditions
     * @param  array<string, ?string>  $other
     * @return list<string>
     */
    public static function summary(array $conditions, array $other = [], ?string $cancerType = null, ?string $cancerTreatment = null): array
    {
        $lines = [];
        foreach (self::GROUPS as $key => [$label, $items]) {
            $names = array_map(fn ($item) => $items[$item] ?? $item, $conditions[$key] ?? []);
            if (filled($other[$key] ?? null)) {
                $names[] = 'Lainnya: '.$other[$key];
            }
            if ($key === 'tumor' && in_array('had_cancer', $conditions[$key] ?? [], true)) {
                $names = array_map(fn ($n) => $n === $items['had_cancer'] ? $n.($cancerType ? " ({$cancerType}" : ' (jenis belum diisi').($cancerTreatment ? ", pengobatan: {$cancerTreatment})" : ')') : $n, $names);
            }
            if ($names) {
                $lines[] = preg_replace('/^[IVX]+\.\s*/', '', $label).': '.implode(', ', $names);
            }
        }

        return $lines;
    }
}

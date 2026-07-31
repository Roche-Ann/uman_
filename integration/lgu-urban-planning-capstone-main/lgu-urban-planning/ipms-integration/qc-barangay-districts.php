<?php
/**
 * Quezon City congressional district -> barangay lookup, used only to fill
 * the `district` field IPMS requires on an inspection request — our own
 * `applications` table has no district column.
 *
 * Ported from IPMS's citizen/includes/qc-locations.php (their canonical
 * source of truth for the same data, used for their citizen dashboard's
 * district/barangay selects). Keep in sync with that file if QC boundaries
 * or barangay names ever change; this is a read-only copy for lookup, not
 * an independent source.
 */

function qcDistrictBarangays(): array
{
    static $districts = null;
    if ($districts !== null) {
        return $districts;
    }

    $districts = [
        'District 1' => [
            'Alicia', 'Bagong Pag-asa', 'Bahay Toro', 'Balingasa', 'Bungad', 'Damar',
            'Damayan', 'Del Monte', 'Katipunan', 'Lourdes', 'Maharlika', 'Manresa',
            'Mariblo', 'Masambong', 'N.S. Amoranto (Gintong Silahis)', 'Nayong Kanluran',
            'Paang Bundok', 'Pag-ibig sa Nayon', 'Paltok', 'Paraiso', 'Phil-Am',
            'Project 6', 'Ramon Magsaysay', 'Saint Peter', 'Salvacion', 'San Antonio',
            'San Isidro Labrador', 'San Jose', 'Santa Cruz', 'Santa Teresita',
            'Sto. Cristo', 'Santo Domingo (Matalahib)', 'Siena', 'Talayan', 'Vasra',
            'Veterans Village', 'West Triangle',
        ],
        'District 2' => [
            'Bagong Silangan', 'Batasan Hills', 'Commonwealth', 'Holy Spirit', 'Payatas',
        ],
        'District 3' => [
            'Amihan', 'Bagumbayan', 'Bagumbuhay', 'Bayanihan', 'Blue Ridge A',
            'Blue Ridge B', 'Camp Aguinaldo', 'Claro (Quirino 3-B)', 'Dioquino Zobel',
            'Duyan-duyan', 'E. Rodriguez', 'East Kamias', 'Escopa I', 'Escopa II',
            'Escopa III', 'Escopa IV', 'Libis', 'Loyola Heights', 'Mangga', 'Marilag',
            'Masagana', 'Matandang Balara', 'Milagrosa', 'Pansol', 'Quirino 2-A',
            'Quirino 2-B', 'Quirino 2-C', 'Quirino 3-A', 'St. Ignatius', 'San Roque',
            'Silangan', 'Socorro', 'Tagumpay', 'Ugong Norte', 'Villa Maria Clara',
            'West Kamias', 'White Plains',
        ],
        'District 4' => [
            'Bagong Lipunan ng Crame', 'Botocan', 'Central', 'Damayang Lagi',
            'Don Manuel', 'Doña Aurora', 'Doña Imelda', 'Doña Josefa', 'Horseshoe',
            'Immaculate Concepcion', 'Kalusugan', 'Kamuning', 'Kaunlaran',
            'Kristong Hari', 'Krus na Ligas', 'Laging Handa', 'Malaya', 'Mariana',
            'Obrero', 'Old Capitol Site', 'Paligsahan', 'Pinagkaisahan', 'Pinyahan',
            'Roxas', 'Sacred Heart', 'San Isidro Galas', 'San Martin de Porres',
            'San Vicente', 'Santol', 'Sikatuna Village', 'South Triangle', 'Sto. Niño',
            'Tatalon', "Teacher's Village East", "Teacher's Village West",
            'U.P. Campus', 'U.P. Village', 'Valencia',
        ],
        'District 5' => [
            'Bagbag', 'Capri', 'Fairview', 'Greater Lagro', 'Gulod', 'Kaligayahan',
            'Nagkaisang Nayon', 'North Fairview', 'Novaliches Proper',
            'Pasong Putik Proper', 'San Agustin', 'San Bartolome', 'Sta. Lucia',
            'Sta. Monica',
        ],
        'District 6' => [
            'Apolonio Samson', 'Baesa', 'Balong Bato', 'Culiat', 'New Era',
            'Pasong Tamo', 'Sangandaan', 'Sauyo', 'Talipapa', 'Tandang Sora',
            'Unang Sigaw',
        ],
    ];

    return $districts;
}

function qcNormalizeBarangayKey(string $value): string
{
    $value = mb_strtolower($value);
    return preg_replace('/[^a-z0-9]/', '', $value);
}

/**
 * @return string|null e.g. "District 3", or null if $barangay doesn't match
 *                     any canonical QC barangay name (case/punctuation-insensitive).
 */
function resolveDistrictForBarangay(?string $barangay): ?string
{
    if (!$barangay) {
        return null;
    }

    static $lookup = null;
    if ($lookup === null) {
        $lookup = [];
        foreach (qcDistrictBarangays() as $district => $barangays) {
            foreach ($barangays as $name) {
                $lookup[qcNormalizeBarangayKey($name)] = $district;
            }
        }
    }

    return $lookup[qcNormalizeBarangayKey($barangay)] ?? null;
}

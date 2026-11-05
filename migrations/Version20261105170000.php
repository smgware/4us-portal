<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Imports the nine Terex Comedil quote-maker machines from the supplied Excel workbook.';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->machines() as $machine) {
            $data = json_encode($machine['data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $this->addSql(
                <<<'SQL'
INSERT INTO quote_maker_machine (title, code, data, datetime_add, datetime_last, uid_add, uid_last)
SELECT ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL, NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM quote_maker_machine
    WHERE code = ?
       OR JSON_UNQUOTE(JSON_EXTRACT(data, '$.megnevezes')) = ?
)
SQL,
                [$machine['title'], $machine['code'], $data, $machine['code'], $machine['title']],
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach ($this->machines() as $machine) {
            $this->addSql('DELETE FROM quote_maker_machine WHERE code = ?', [$machine['code']]);
        }
    }

    /**
     * @return array<int, array{title: string, code: string, data: array<string, int|float|string>}>
     */
    private function machines(): array
    {
        $defaults = [
            'megnevezes' => '',
            'tipus' => '',
            'evjarat' => '',
            'horogmagassag' => '',
            'gemkinyulas' => '',
            'max_teherbiras' => '',
            'telepitesi_szint' => '',
            'telepitesi_mod' => '',
            'elektromos_csatlakozas' => '',
            'havi_dij_200_uo_ig' => '',
            '200_uo_felett_ora' => 9750,
            'kezelo_7_17' => 7350,
            'kezelo_17_7' => 9900,
            'kezelo_vasarnap_unnep' => 14700,
            'kapcsoloszekreny_ho' => '',
            'telepites_egyszeri_dij' => '',
            'bontas_egyszeri_dij' => '',
            'veszelymentes_tervirat_egyszeri_dij' => 150000,
            'uzembe_helyezes_ev' => 300000,
            'haz_magassaga' => 10,
            'telepitesi_szint_mag' => -4,
            'haz_absz_mag' => 14,
            'vedotav' => 6,
            'tenyleges_mag' => 20,
            'absz_mag_daru' => 16,
            'telepitheto_autodaru_max_10m' => '',
            'anker_elhelyezeshez_csavarok_szama' => '',
            'anker_elhelyezes_dija_eur' => '',
            'anker_elhelyezes_dija_huf' => '',
            'beszerzesi_ar' => '',
            'ar_eur' => '',
            'ar_huf' => '',
            'legnagyobb_suly' => '',
        ];

        $machine = static fn (string $title, string $code, array $data): array => [
            'title' => $title,
            'code' => $code,
            'data' => array_replace($defaults, $data),
        ];

        return [
            $machine('Terex Comedil CTT 91-5 (2007)', 'TEREX-COMEDIL-CTT-91-5-2007', [
                'megnevezes' => 'Terex Comedil CTT 91-5 (2007)',
                'tipus' => 'Terex Comedil CTT 91-5',
                'evjarat' => 2007,
                'horogmagassag' => 17.25,
                'gemkinyulas' => '2,3 t / 40 m',
                'max_teherbiras' => '5 t / 19,58 m-ig',
                'telepitesi_szint' => -4,
                'telepitesi_mod' => 'Alváz (3,8 x 3,8 m)',
                'elektromos_csatlakozas' => '3x63 A',
                'havi_dij_200_uo_ig' => 1450000,
                'kapcsoloszekreny_ho' => 90000,
                'telepites_egyszeri_dij' => 4200000,
                'bontas_egyszeri_dij' => 4200000,
                'telepitheto_autodaru_max_10m' => 80,
                'anker_elhelyezeshez_csavarok_szama' => 16,
                'anker_elhelyezes_dija_eur' => 1400,
                'anker_elhelyezes_dija_huf' => 539000,
                'beszerzesi_ar' => 75000,
                'ar_eur' => 3125,
                'ar_huf' => 1203125,
                'legnagyobb_suly' => '5934 kg.',
            ]),
            $machine('Terex Comedil CTT 91-5 (2021)', 'TEREX-COMEDIL-CTT-91-5-2021', [
                'megnevezes' => 'Terex Comedil CTT 91-5 (2021)',
                'tipus' => 'Terex Comedil CTT 91-5',
                'evjarat' => 2021,
                'horogmagassag' => 17.25,
                'gemkinyulas' => '2,3 t / 40 m',
                'max_teherbiras' => '5 t / 19,58 m-ig',
                'telepitesi_szint' => -4,
                'telepitesi_mod' => 'Alváz (3,8 x 3,8 m)',
                'elektromos_csatlakozas' => '3x63 A',
                'havi_dij_200_uo_ig' => 1450000,
                'kapcsoloszekreny_ho' => 90000,
                'telepites_egyszeri_dij' => 4200000,
                'bontas_egyszeri_dij' => 4200000,
                'tenyleges_mag' => 17.25,
                'absz_mag_daru' => 13.25,
                'telepitheto_autodaru_max_10m' => 80,
                'anker_elhelyezeshez_csavarok_szama' => 16,
                'anker_elhelyezes_dija_eur' => 1400,
                'anker_elhelyezes_dija_huf' => 539000,
                'beszerzesi_ar' => 75000,
                'ar_eur' => 3125,
                'ar_huf' => 1203125,
                'legnagyobb_suly' => '5934 kg.',
            ]),
            $machine('Terex Comedil CTT 132-6 (2019)', 'TEREX-COMEDIL-CTT-132-6-2019', [
                'megnevezes' => 'Terex Comedil CTT 132-6 (2019)',
                'tipus' => 'Terex Comedil CTT 132-6',
                'evjarat' => 2019,
                'telepitesi_mod' => 'Alváz (3,8 x 3,8 m)',
                'elektromos_csatlakozas' => '3x100 A',
                'havi_dij_200_uo_ig' => 1950000,
                'kapcsoloszekreny_ho' => 120000,
                'telepites_egyszeri_dij' => 4200000,
                'bontas_egyszeri_dij' => 4200000,
                'telepitheto_autodaru_max_10m' => 120,
                'anker_elhelyezeshez_csavarok_szama' => 24,
                'anker_elhelyezes_dija_eur' => 2150,
                'anker_elhelyezes_dija_huf' => 827750,
                'beszerzesi_ar' => 105000,
                'ar_eur' => 4375,
                'ar_huf' => 1684375,
                'legnagyobb_suly' => '7310 kg.',
            ]),
            $machine('Terex Comedil CTT 132-6 (2020)', 'TEREX-COMEDIL-CTT-132-6-2020', [
                'megnevezes' => 'Terex Comedil CTT 132-6 (2020)',
                'tipus' => 'Terex Comedil CTT 132-6',
                'evjarat' => 2020,
                'telepitesi_mod' => 'Alváz (3,8 x 3,8 m)',
                'elektromos_csatlakozas' => '3x100 A',
                'havi_dij_200_uo_ig' => 1950000,
                'kapcsoloszekreny_ho' => 120000,
                'telepites_egyszeri_dij' => 4200000,
                'bontas_egyszeri_dij' => 4200000,
                'telepitheto_autodaru_max_10m' => 120,
                'anker_elhelyezeshez_csavarok_szama' => 24,
                'anker_elhelyezes_dija_eur' => 2150,
                'anker_elhelyezes_dija_huf' => 827750,
                'beszerzesi_ar' => 105000,
                'ar_eur' => 4375,
                'ar_huf' => 1684375,
                'legnagyobb_suly' => '7310 kg.',
            ]),
            $machine('Terex Comedil CTT 162-8 (2017)', 'TEREX-COMEDIL-CTT-162-8-2017', [
                'megnevezes' => 'Terex Comedil CTT 162-8 (2017)',
                'tipus' => 'Terex Comedil CTT 162-8',
                'evjarat' => 2017,
                'telepitesi_mod' => 'Alváz (4,5 x 4,5 m)',
                'elektromos_csatlakozas' => '3x100 A',
                'havi_dij_200_uo_ig' => 2640000,
                'kapcsoloszekreny_ho' => 120000,
                'telepites_egyszeri_dij' => 4200000,
                'bontas_egyszeri_dij' => 4200000,
                'telepitheto_autodaru_max_10m' => 120,
                'anker_elhelyezeshez_csavarok_szama' => 32,
                'anker_elhelyezes_dija_eur' => 2800,
                'anker_elhelyezes_dija_huf' => 1078000,
                'beszerzesi_ar' => 165000,
                'ar_eur' => 6875,
                'ar_huf' => 2646875,
                'legnagyobb_suly' => '6985 kg.',
            ]),
            $machine('Terex Comedil CTT 182-8', 'TEREX-COMEDIL-CTT-182-8', [
                'megnevezes' => 'Terex Comedil CTT 182-8',
                'tipus' => 'Terex Comedil CTT 182-8',
                'telepitesi_mod' => 'Alváz (4,5 x 4,5 m)',
                'elektromos_csatlakozas' => '3x125 A',
                'havi_dij_200_uo_ig' => 2640000,
                'kapcsoloszekreny_ho' => 150000,
                'telepites_egyszeri_dij' => 2950000,
                'bontas_egyszeri_dij' => 2950000,
                'telepitheto_autodaru_max_10m' => 150,
                'anker_elhelyezes_dija_eur' => 6500,
                'anker_elhelyezes_dija_huf' => 2502500,
                'beszerzesi_ar' => 195000,
                'ar_eur' => 8125,
                'ar_huf' => 3128125,
                'legnagyobb_suly' => '7000 kg.',
            ]),
            $machine('Terex Comedil CTT 231-12', 'TEREX-COMEDIL-CTT-231-12', [
                'megnevezes' => 'Terex Comedil CTT 231-12',
                'tipus' => 'Terex Comedil CTT 231-12',
                'telepitesi_mod' => 'Alváz (6 x 6 m)',
                'elektromos_csatlakozas' => '3x160 A',
                'havi_dij_200_uo_ig' => 3450000,
                'kapcsoloszekreny_ho' => 150000,
                'telepites_egyszeri_dij' => 2950000,
                'bontas_egyszeri_dij' => 2950000,
                'telepitheto_autodaru_max_10m' => 200,
                'anker_elhelyezes_dija_eur' => 12000,
                'anker_elhelyezes_dija_huf' => 4620000,
                'beszerzesi_ar' => 220000,
                'ar_eur' => 9166.666666666666,
                'ar_huf' => 3529166.6666666665,
                'legnagyobb_suly' => '7700 kg.',
            ]),
            $machine('Terex Comedil CTT 332-16', 'TEREX-COMEDIL-CTT-332-16', [
                'megnevezes' => 'Terex Comedil CTT 332-16',
                'tipus' => 'Terex Comedil CTT 332-16',
                'telepitesi_mod' => 'Alváz (8 x 8 m)',
                'elektromos_csatlakozas' => '3x160 A',
                'havi_dij_200_uo_ig' => 4500000,
                'kapcsoloszekreny_ho' => 180000,
                'telepites_egyszeri_dij' => 2950000,
                'bontas_egyszeri_dij' => 2950000,
                'telepitheto_autodaru_max_10m' => 200,
                'anker_elhelyezes_dija_eur' => 16000,
                'anker_elhelyezes_dija_huf' => 6160000,
                'beszerzesi_ar' => 400000,
                'ar_eur' => 16666.666666666668,
                'ar_huf' => 6416666.666666667,
                'legnagyobb_suly' => '8560 kg.',
            ]),
            $machine('Terex Comedil CTT 472-20', 'TEREX-COMEDIL-CTT-472-20', [
                'megnevezes' => 'Terex Comedil CTT 472-20',
                'tipus' => 'Terex Comedil CTT 472-20',
                'telepitesi_mod' => 'Alváz (10 x 10 m)',
                'elektromos_csatlakozas' => '3x250 A',
                'havi_dij_200_uo_ig' => 6800000,
                'kapcsoloszekreny_ho' => 180000,
                'telepites_egyszeri_dij' => 2950000,
                'bontas_egyszeri_dij' => 2950000,
                'telepitheto_autodaru_max_10m' => 250,
                'anker_elhelyezes_dija_eur' => 16000,
                'anker_elhelyezes_dija_huf' => 6160000,
                'beszerzesi_ar' => 600000,
                'ar_eur' => 25000,
                'ar_huf' => 9625000,
                'legnagyobb_suly' => '10970 kg.',
            ]),
        ];
    }
}

<?php

namespace App\Database\Seeds;

use App\Models\ItemCategoryModel;
use App\Models\ItemUnitModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class ItemSeeder extends Seeder
{
    public function run()
    {
        $categoryModel = new ItemCategoryModel();
        $itemUnitModel = new ItemUnitModel();

        $categoryIds = $this->resolveRequiredCategoryIds($categoryModel, ['BASAH', 'KERING', 'PENGEMAS']);

        $units = [
            'gram'    => $this->resolveRequiredUnitId($itemUnitModel, 'gram'),
            'kg'      => $this->resolveRequiredUnitId($itemUnitModel, 'kg'),
            'ml'      => $this->resolveRequiredUnitId($itemUnitModel, 'ml'),
            'liter'   => $this->resolveRequiredUnitId($itemUnitModel, 'liter'),
            'butir'   => $this->resolveRequiredUnitId($itemUnitModel, 'butir'),
            'btr'     => $this->resolveRequiredUnitId($itemUnitModel, 'btr'),
            'pack'    => $this->resolveRequiredUnitId($itemUnitModel, 'pack'),
            'pcs'     => $this->resolveRequiredUnitId($itemUnitModel, 'pcs'),
            'roll'    => $this->resolveRequiredUnitId($itemUnitModel, 'roll'),
            'bks'     => $this->resolveRequiredUnitId($itemUnitModel, 'bks'),
            'ssr'     => $this->resolveRequiredUnitId($itemUnitModel, 'ssr'),
            'ons'     => $this->resolveRequiredUnitId($itemUnitModel, 'ons'),
            'ikt'     => $this->resolveRequiredUnitId($itemUnitModel, 'ikt'),
            'sachet'  => $this->resolveRequiredUnitId($itemUnitModel, 'sachet'),
            'dus'     => $this->resolveRequiredUnitId($itemUnitModel, 'dus'),
            'kotak'   => $this->resolveRequiredUnitId($itemUnitModel, 'kotak'),
            'kaleng'  => $this->resolveRequiredUnitId($itemUnitModel, 'kaleng'),
            'bungkus' => $this->resolveRequiredUnitId($itemUnitModel, 'bungkus'),
            'jurigen' => $this->resolveRequiredUnitId($itemUnitModel, 'jurigen'),
            'botol'   => $this->resolveRequiredUnitId($itemUnitModel, 'botol'),
            'pace'    => $this->resolveRequiredUnitId($itemUnitModel, 'pace'),
        ];

        $items = [
            // PENGEMAS
            ['cat' => 'PENGEMAS', 'name' => 'Sendok Puding',         'unit' => 'pack', 'qty' => 2,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Plastik Opp 14x14',     'unit' => 'pcs',  'qty' => 11,   'min_stock' => 5],
            ['cat' => 'PENGEMAS', 'name' => 'Plastik Opp 11x11',     'unit' => 'pcs',  'qty' => 14,   'min_stock' => 5],
            ['cat' => 'PENGEMAS', 'name' => 'Tusuk Gigi',            'unit' => 'pack', 'qty' => 5,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Gelas Kertas',          'unit' => 'pack', 'qty' => 12,   'min_stock' => 5],
            ['cat' => 'PENGEMAS', 'name' => 'Sarung Tangan Plastik', 'unit' => 'pack', 'qty' => 1,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Mika Buah Kotak',       'unit' => 'pack', 'qty' => 8,    'min_stock' => 3],
            ['cat' => 'PENGEMAS', 'name' => 'Tisu Makan',            'unit' => 'pack', 'qty' => 4,    'min_stock' => 3],
            ['cat' => 'PENGEMAS', 'name' => 'Sendok Plastik',        'unit' => 'pack', 'qty' => 1,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Cup Puding Plastik',    'unit' => 'pack', 'qty' => 1,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Plastik 0.25 Kg',       'unit' => 'pcs',  'qty' => 41,   'min_stock' => 5],
            ['cat' => 'PENGEMAS', 'name' => 'Plastik 0.5 Kg',        'unit' => 'pack', 'qty' => 20,   'min_stock' => 5],
            ['cat' => 'PENGEMAS', 'name' => 'Mika 7c/Mini Tart',     'unit' => 'pack', 'qty' => 6,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Garpu Buah',            'unit' => 'pack', 'qty' => 4,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Sterofoam',             'unit' => 'pack', 'qty' => 2,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Cup Kertas Kue Lumpur', 'unit' => 'pack', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Gelas 10 Oz',           'unit' => 'pack', 'qty' => 3,    'min_stock' => 3],
            ['cat' => 'PENGEMAS', 'name' => 'Plastik 2 Kg',          'unit' => 'pcs',  'qty' => 25,   'min_stock' => 5],
            ['cat' => 'PENGEMAS', 'name' => 'Sendok Bebek Plastik',  'unit' => 'pack', 'qty' => 4,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Plastik Cup Sealer',    'unit' => 'pcs',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'PENGEMAS', 'name' => 'Plastik Clip',          'unit' => 'pack', 'qty' => 1,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Plastik Wrap/Cling',    'unit' => 'roll', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'PENGEMAS', 'name' => 'Sabun Cair Sunlight',   'unit' => 'pcs',  'qty' => 0,    'min_stock' => 2],

            // BASAH
            ['cat' => 'BASAH', 'name' => 'Bakso Sapi',      'unit' => 'bks', 'qty' => 11,   'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Daging Ayam',     'unit' => 'kg',  'qty' => 0,    'min_stock' => 10],
            ['cat' => 'BASAH', 'name' => 'Daging Sapi',     'unit' => 'kg',  'qty' => 0,    'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Tahu',            'unit' => 'pcs', 'qty' => 0,    'min_stock' => 10],
            ['cat' => 'BASAH', 'name' => 'Telur',           'unit' => 'kg',  'qty' => 11.5, 'min_stock' => 10],
            ['cat' => 'BASAH', 'name' => 'Tempe',           'unit' => 'kg',  'qty' => 0,    'min_stock' => 10],
            ['cat' => 'BASAH', 'name' => 'Tengiri Potong',  'unit' => 'kg',  'qty' => 0,    'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Tongkol',         'unit' => 'kg',  'qty' => 0,    'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Bayam',           'unit' => 'kg',  'qty' => 0,    'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Buncis',          'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Bunga Kol',       'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Gambas',          'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Kacang Panjang',  'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Kentang',         'unit' => 'kg',  'qty' => 0,    'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Ketimun',         'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Labu Siam',       'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Sawi Hijau',      'unit' => 'kg',  'qty' => 0,    'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Sawi Putih',      'unit' => 'kg',  'qty' => 0,    'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Tauge Pendek',    'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Wortel',          'unit' => 'kg',  'qty' => 0,    'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Kol',             'unit' => 'kg',  'qty' => 0,    'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Melon',           'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Pepaya',          'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Pisang Ambon',    'unit' => 'ssr', 'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Semangka',        'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Bawang Merah',    'unit' => 'kg',  'qty' => 0,    'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Bawang Putih',    'unit' => 'kg',  'qty' => 0,    'min_stock' => 5],
            ['cat' => 'BASAH', 'name' => 'Cabe Merah',      'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Bawang Prey',     'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Kluwek',          'unit' => 'kg',  'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Jahe',            'unit' => 'kg',  'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Lengkuas',        'unit' => 'kg',  'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Kunyit',          'unit' => 'kg',  'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Kunci',           'unit' => 'kg',  'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Daun Jeruk',      'unit' => 'ons', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Sereh',           'unit' => 'kg',  'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Daun Pisang',     'unit' => 'ikt', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Seledri',         'unit' => 'ons', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Tomat',           'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Asem',            'unit' => 'ons', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Jagung Manis',    'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Kemangi',         'unit' => 'ikt', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Kelapa Parut',    'unit' => 'btr', 'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Terong',          'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Kacang Tanah',    'unit' => 'ons', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Pala',            'unit' => 'kg',  'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Pisang Kepok',    'unit' => 'ssr', 'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Daun Salam',      'unit' => 'ikt', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Edamame Kedelai', 'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],
            ['cat' => 'BASAH', 'name' => 'Pandan Wangi',    'unit' => 'ikt', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH', 'name' => 'Selada',          'unit' => 'kg',  'qty' => 0,    'min_stock' => 3],

            // KERING
            ['cat' => 'KERING', 'name' => 'Beras',                            'unit' => 'kg',      'qty' => 209,  'min_stock' => 50],
            ['cat' => 'KERING', 'name' => 'Tepung Beras',                     'unit' => 'kg',      'qty' => 12.8, 'min_stock' => 5],
            ['cat' => 'KERING', 'name' => 'Tepung Terigu',                    'unit' => 'kg',      'qty' => 8.75, 'min_stock' => 5],
            ['cat' => 'KERING', 'name' => 'Tepung Panir',                     'unit' => 'kg',      'qty' => 1,    'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Tepung Kanji 500gr',               'unit' => 'kg',      'qty' => 1.5,  'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Tepung Bumbu',                     'unit' => 'pcs',     'qty' => 2,    'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Tepung Maezena',                   'unit' => 'kg',      'qty' => 1,    'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Tepung Hungkwe',                   'unit' => 'bungkus', 'qty' => 15,   'min_stock' => 5],
            ['cat' => 'KERING', 'name' => 'Gula Pasir',                       'unit' => 'kg',      'qty' => 9.5,  'min_stock' => 10],
            ['cat' => 'KERING', 'name' => 'Gula Merah',                       'unit' => 'kg',      'qty' => 13.3, 'min_stock' => 5],
            ['cat' => 'KERING', 'name' => 'Minyak Goreng',                    'unit' => 'liter',   'qty' => 31,   'min_stock' => 10],
            ['cat' => 'KERING', 'name' => 'Lada Bubuk',                       'unit' => 'sachet',  'qty' => 107,  'min_stock' => 10],
            ['cat' => 'KERING', 'name' => 'Kemiri',                           'unit' => 'kg',      'qty' => 2,    'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Ketumbar Bubuk',                   'unit' => 'sachet',  'qty' => 12,   'min_stock' => 5],
            ['cat' => 'KERING', 'name' => 'Garam Halus',                      'unit' => 'pcs',     'qty' => 40,   'min_stock' => 10],
            ['cat' => 'KERING', 'name' => 'Garam Lososa',                     'unit' => 'pcs',     'qty' => 1,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Kecap Manis',                      'unit' => 'sachet',  'qty' => 223,  'min_stock' => 20],
            ['cat' => 'KERING', 'name' => 'Air Mineral Gelas Alqodiri',       'unit' => 'dus',     'qty' => 16,   'min_stock' => 5],
            ['cat' => 'KERING', 'name' => 'Susu Dancow',                      'unit' => 'kg',      'qty' => 0,    'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Susu Hepatosol',                   'unit' => 'kotak',   'qty' => 6,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Susu Diabetasol',                  'unit' => 'kotak',   'qty' => 1,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Susu Neprisol',                    'unit' => 'kotak',   'qty' => 5,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Susu Neocate',                     'unit' => 'kaleng',  'qty' => 1,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Susu Proten',                      'unit' => 'kotak',   'qty' => 40,   'min_stock' => 5],
            ['cat' => 'KERING', 'name' => 'Susu Entrasol Platinum',           'unit' => 'kaleng',  'qty' => 1,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Susu Lactogen Llf',                'unit' => 'kaleng',  'qty' => 1,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Susu Sgm Ananda Gain 0-12',        'unit' => 'kotak',   'qty' => 12,   'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Susu Sgm Gain Optigrow 1+',        'unit' => 'kotak',   'qty' => 12,   'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Susu Neprisol D',                  'unit' => 'kaleng',  'qty' => 3,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Susu S26',                         'unit' => 'kaleng',  'qty' => 2,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Susu Lactogen Premature',          'unit' => 'kaleng',  'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Gula Diabetasol',                  'unit' => 'kotak',   'qty' => 3,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Bihun',                            'unit' => 'bungkus', 'qty' => 3,    'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Makaroni',                         'unit' => 'kg',      'qty' => 0.8,  'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Santan Kara',                      'unit' => 'pcs',     'qty' => 49,   'min_stock' => 5],
            ['cat' => 'KERING', 'name' => 'Saus Tomat Delmonte',              'unit' => 'jurigen', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Teh Celup Sariwangi',              'unit' => 'kotak',   'qty' => 2,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Sp',                               'unit' => 'sachet',  'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Baking Powder',                    'unit' => 'sachet',  'qty' => 7,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Fermipan',                         'unit' => 'sachet',  'qty' => 8,    'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Margarin',                         'unit' => 'kaleng',  'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Choco Chip',                       'unit' => 'kg',      'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Meses',                            'unit' => 'kg',      'qty' => 1.25, 'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Vanili',                           'unit' => 'sachet',  'qty' => 200,  'min_stock' => 10],
            ['cat' => 'KERING', 'name' => 'Agar-Agar Warna Putih',            'unit' => 'sachet',  'qty' => 15,   'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Baking Soda',                      'unit' => 'bungkus', 'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Pewarna Makanan Kuning Telur',     'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Pewarna Makanan Hijau',            'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Sagu Mutiara',                     'unit' => 'pcs',     'qty' => 23,   'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Sirup Marjan Cocopandan',          'unit' => 'botol',   'qty' => 2,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Sirup Marjan Melon',               'unit' => 'botol',   'qty' => 2,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Susu Kental Manis Putih Indomilk', 'unit' => 'kaleng',  'qty' => 3,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Kopi Nescafe',                     'unit' => 'pace',    'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Esence Jeruk',                     'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Pasta Pandan',                     'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Butter Krim',                      'unit' => 'kg',      'qty' => 0.25, 'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Kismis',                           'unit' => 'kg',      'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Coklat Bubuk',                     'unit' => 'kg',      'qty' => 0.5,  'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Kacang Hijau',                     'unit' => 'kg',      'qty' => 2.5,  'min_stock' => 3],
            ['cat' => 'KERING', 'name' => 'Sprite',                           'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Saos Raja Rasa',                   'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Maggi Seasoning',                  'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Esence Strawberi',                 'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Esence Melon',                     'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Esence Leci',                      'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Pewarna Merah Muda',               'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'KERING', 'name' => 'Pasta Coklat',                     'unit' => 'botol',   'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH',  'name' => 'Daun Kelor',                       'unit' => 'kg',      'qty' => 0,    'min_stock' => 2],
            ['cat' => 'BASAH',  'name' => 'Ayam Giling',                      'unit' => 'kg',      'qty' => 0,    'min_stock' => 5],
            ['cat' => 'KERING', 'name' => 'Kacang Merah',                     'unit' => 'kg',      'qty' => 0,    'min_stock' => 3],
        ];

        $batch = [];

        foreach ($items as $item) {
            $unitConvert = $item['unit'];
            $unitBase    = $unitConvert;
            $convBase    = 1;

            if ($unitConvert === 'kg') {
                $unitBase = 'gram';
                $convBase = 1000;
            } elseif ($unitConvert === 'liter') {
                $unitBase = 'ml';
                $convBase = 1000;
            } elseif ($unitConvert === 'ons') {
                $unitBase = 'gram';
                $convBase = 100;
            }

            $batch[] = [
                'item_category_id'     => $categoryIds[$item['cat']],
                'name'                 => $item['name'],
                'unit_base'            => $unitBase,
                'unit_convert'         => $unitConvert,
                'item_unit_base_id'    => $units[$unitBase],
                'item_unit_convert_id' => $units[$unitConvert],
                'conversion_base'      => $convBase,
                'is_active'            => true,
                'qty'                  => $item['qty'],
                'min_stock'            => $item['min_stock'] ?? 0,
            ];
        }

        $this->db->table('items')->insertBatch($batch);
    }

    /**
     * @param list<string> $requiredNames
     *
     * @return array<string, int>
     */
    private function resolveRequiredCategoryIds(ItemCategoryModel $categoryModel, array $requiredNames): array
    {
        $rows = $categoryModel->select('id, name')->findAll();

        $categoryLookup = [];

        foreach ($rows as $row) {
            $categoryLookup[strtoupper(trim((string) $row['name']))] = (int) $row['id'];
        }

        $resolved = [];

        foreach ($requiredNames as $name) {
            $key = strtoupper(trim($name));

            if (! array_key_exists($key, $categoryLookup)) {
                throw new RuntimeException("ItemSeeder prerequisite missing: item_categories.name '{$name}'. Seed ItemCategorySeeder before ItemSeeder.");
            }

            $resolved[$name] = $categoryLookup[$key];
        }

        return $resolved;
    }

    private function resolveRequiredUnitId(ItemUnitModel $itemUnitModel, string $unitName): int
    {
        $unitId = $itemUnitModel->getIdByName($unitName);

        if ($unitId === null) {
            throw new RuntimeException("ItemSeeder prerequisite missing: item_units.name '{$unitName}'. Seed ItemUnitSeeder before ItemSeeder.");
        }

        return (int) $unitId;
    }
}

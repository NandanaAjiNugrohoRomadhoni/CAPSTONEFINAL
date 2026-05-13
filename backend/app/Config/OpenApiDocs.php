<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class OpenApiDocs extends BaseConfig
{
    /**
     * @var list<string>
     */
     public array $sourceFiles = [
         APPPATH . 'OpenApi/OpenApiSpec.php',
         APPPATH . 'OpenApi/CommonSchemas.php',
         APPPATH . 'OpenApi/ResourceSchemas.php',
         APPPATH . 'OpenApi/MenuCoreSchemas.php',
         APPPATH . 'OpenApi/DailyPatientSchemas.php',
         APPPATH . 'OpenApi/DashboardSchemas.php',
         APPPATH . 'OpenApi/StockTransactionSchemas.php',
         APPPATH . 'OpenApi/SpkBasahSchemas.php',
        APPPATH . 'Controllers/Api/V1/Auth.php',
        APPPATH . 'Controllers/Api/V1/Items.php',
        APPPATH . 'Controllers/Api/V1/ItemCategories.php',
        APPPATH . 'Controllers/Api/V1/TransactionTypes.php',
        APPPATH . 'Controllers/Api/V1/ApprovalStatuses.php',
        APPPATH . 'Controllers/Api/V1/MealTimes.php',
        APPPATH . 'Controllers/Api/V1/ItemUnits.php',
         APPPATH . 'Controllers/Api/V1/Roles.php',
         APPPATH . 'Controllers/Api/V1/Users.php',
         APPPATH . 'Controllers/Api/V1/Dishes.php',
         APPPATH . 'Controllers/Api/V1/DishCompositions.php',
         APPPATH . 'Controllers/Api/V1/Menus.php',
         APPPATH . 'Controllers/Api/V1/DailyPatients.php',
         APPPATH . 'Controllers/Api/V1/Dashboard.php',
         APPPATH . 'Controllers/Api/V1/StockTransactions.php',
         APPPATH . 'Controllers/Api/V1/SpkBasah.php',
         APPPATH . 'Controllers/Api/V1/SpkKeringPengemas.php',
         APPPATH . 'Controllers/Api/V1/SpkStockInPrefill.php',
     ];

    public string $cachePath = WRITEPATH . 'cache/openapi-spec.json';

    public string $openApiVersion = '3.1.0';
}

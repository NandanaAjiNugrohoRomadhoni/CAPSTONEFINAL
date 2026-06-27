<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
// $routes->get("/", "Home::index");
$routes->get("api/docs/spec", "DocsController::spec");
$routes->get("api/docs/specs", "DocsController::spec");
$routes->get("api/docs", "DocsController::index");

$routes->group(
    "api/v1",
    ["namespace" => "App\Controllers\Api\V1", "filter" => "cors"],
    static function ($routes) {
        $routes->post("auth/login", "Auth::login");
        $routes->options(
            "auth/login",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "auth/me",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "auth/logout",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "auth/password",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "notifications",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "notifications/(:num)/read",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "notifications/read-all",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "notifications/(:num)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "roles",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "item-categories",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "item-categories/(:num)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "transaction-types",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "approval-statuses",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "meal-times",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "item-units",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "item-units/(:num)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "dishes",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "dishes/(:num)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "dishes/(:num)/deactivate",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "dishes/(:num)/reactivate",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "dish-compositions",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "dish-compositions/(:num)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "menus",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "menu-dishes",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "menu-dishes/(:num)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "menu-schedules",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "menu-schedules/(:num)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "menu-calendar",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "daily-patients",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "daily-patients/(:segment)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/basah/menu-calendar",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/basah/generate",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/basah/operational-stock-preview",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/basah/history",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/basah/history/(:num)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/basah/history/(:num)/post-stock",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/basah/history/(:num)/override",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/kering-pengemas/menu-calendar",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/kering-pengemas/generate",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/kering-pengemas/history",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/kering-pengemas/history/(:num)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/kering-pengemas/history/(:num)/post-stock",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/kering-pengemas/history/(:num)/override",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "spk/stock-in-prefill/(:num)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "item-categories/(:num)/restore",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "item-units/(:num)/restore",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "items/(:num)/restore",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "users/(:num)/restore",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "stock-opnames",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "stock-opnames/(:num)",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "stock-opnames/(:num)/submit",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "stock-opnames/(:num)/approve",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "stock-opnames/(:num)/reject",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "stock-opnames/(:num)/post",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "audit-logs",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "audit-logs/summary",
            static fn() => service("response")->setStatusCode(204),
        );

        $routes->options(
            "dashboard",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "reports/stocks",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "reports/transactions",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "reports/spk-history",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "reports/evaluation",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "reports/monthly-stock-export",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "stock-snapshots",
            static fn() => service("response")->setStatusCode(204),
        );
        $routes->options(
            "stock-snapshots/current",
            static fn() => service("response")->setStatusCode(204),
        );

        if (ENVIRONMENT === "testing") {
            $routes->get(
                "test/unhandled-exception",
                "TestFailure::triggerUnhandledException",
            );
            $routes->get("test/not-found", "TestFailure::triggerNotFound");
        }

        $routes->group("", ["filter" => "tokens"], static function ($routes) {
            // [MODULE: Auth] Roles: authenticated | Controller: App\Controllers\Api\V1\Auth
            $routes->get("auth/me", "Auth::me");
            $routes->post("auth/logout", "Auth::logout");
            $routes->patch("auth/password", "Auth::changePassword");

            $routes->get("notifications", "Notifications::index");
            $routes->post(
                "notifications/read-all",
                "Notifications::markAllAsRead",
            );
            $routes->post(
                "notifications/(:num)/read",
                'Notifications::markAsRead/$1',
            );
            $routes->delete("notifications/(:num)", 'Notifications::delete/$1');
            $routes->delete("notifications", "Notifications::deleteAll");

            $routes->group(
                "",
                ["filter" => "role:admin,dapur,gudang"],
                static function ($routes) {
                    // [MODULE: Dashboard & Reports] Roles: admin, dapur, gudang | Controller: App\Controllers\Api\V1\Dashboard, App\Controllers\Api\V1\Reports
                    $routes->get("dashboard", "Dashboard::index");
                    $routes->get("reports/stocks", "Reports::stocks");
                    $routes->get(
                        "reports/transactions",
                        "Reports::transactions",
                    );
                    $routes->get("reports/spk-history", "Reports::spkHistory");
                    $routes->get("reports/evaluation", "Reports::evaluation");
                    $routes->get(
                        "reports/monthly-stock-export",
                        "Reports::monthlyStockExport",
                    );
                    $routes->get(
                        "stock-snapshots/current",
                        "StockSnapshots::current",
                    );
                },
            );

            // [MODULE: Audit Logs] Roles: admin | Controller: App\Controllers\Api\V1\AuditLogs
            $routes->group(
                "",
                ["filter" => "role:admin"],
                static function ($routes) {
                $routes->get("audit-logs", "AuditLogs::index");
                $routes->get("audit-logs/types", "AuditLogs::types");
                $routes->get("audit-logs/summary", "AuditLogs::summary");
            },
            );

            // [MODULE: Menu & Nutrition Read Surface] Roles: admin, dapur, gudang | Controller: App\Controllers\Api\V1\Dishes, DishCompositions, Menus, MenuSchedules, DailyPatients, SpkBasah, SpkKeringPengemas
            $routes->group(
                "",
                ["filter" => "role:admin,dapur,gudang"],
                static function ($routes) {
                $routes->get("dishes", "Dishes::index");
                $routes->get("dishes/(:num)", 'Dishes::show/$1');

                $routes->get(
                    "dish-compositions",
                    "DishCompositions::index",
                );
                $routes->get(
                    "dish-compositions/(:num)",
                    'DishCompositions::show/$1',
                );

                $routes->get("menus", "Menus::index");
                $routes->get("menu-dishes", "Menus::slots");
                $routes->get("menu-schedules", "MenuSchedules::index");
                $routes->get(
                    "menu-schedules/(:num)",
                    'MenuSchedules::show/$1',
                );
                $routes->get("menu-calendar", "MenuSchedules::calendarProjection");
                $routes->get("daily-patients", "DailyPatients::index");
                $routes->get(
                    "daily-patients/(:segment)",
                    'DailyPatients::show/$1',
                );

                $routes->get("spk/basah/menu-calendar", "SpkBasah::menuCalendarProjection");
                $routes->get("spk/basah/history", "SpkBasah::history");
                $routes->get("spk/basah/history/(:num)", 'SpkBasah::show/$1');

                $routes->get("spk/kering-pengemas/menu-calendar", "SpkKeringPengemas::menuCalendarProjection");
                $routes->get("spk/kering-pengemas/history", "SpkKeringPengemas::history");
                $routes->get("spk/kering-pengemas/history/(:num)", 'SpkKeringPengemas::show/$1');
            },
            );

            // [MODULE: Inventory Lookup Read Surface] Roles: admin, dapur, gudang | Controller: App\Controllers\Api\V1\ItemCategories, TransactionTypes, ApprovalStatuses, MealTimes, ItemUnits, Items
            $routes->group(
                "",
                ["filter" => "role:admin,dapur,gudang"],
                static function ($routes) {
                $routes->get("item-categories", "ItemCategories::index");
                $routes->get(
                    "item-categories/(:num)",
                    'ItemCategories::show/$1',
                );
                $routes->get(
                    "transaction-types",
                    "TransactionTypes::index",
                );
                $routes->get(
                    "approval-statuses",
                    "ApprovalStatuses::index",
                );
                $routes->get("meal-times", "MealTimes::index");
                $routes->get("item-units", "ItemUnits::index");
                $routes->get("item-units/(:num)", 'ItemUnits::show/$1');
                $routes->get("items", "Items::index");
                $routes->get("items/(:num)", 'Items::show/$1');
            },
            );

            // [MODULE: Inventory & Stock Operations] Roles: admin, gudang | Controller: App\Controllers\Api\V1\Items, StockTransactions, StockOpnames
            $routes->group(
                "",
                ["filter" => "role:admin,gudang"],
                static function ($routes) {

                $routes->options(
                    "items",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->post("items", "Items::create");
                $routes->options(
                    "items/(:num)",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->put("items/(:num)", 'Items::update/$1');

                $routes->get(
                    "stock-snapshots",
                    "StockSnapshots::index",
                );
                $routes->post(
                    "stock-snapshots",
                    "StockSnapshots::take",
                );
                $routes->get(
                    "stock-transactions",
                    "StockTransactions::index",
                );
                $routes->options(
                    "stock-transactions",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->post(
                    "stock-transactions",
                    "StockTransactions::create",
                );
                $routes->get(
                    "stock-transactions/(:num)",
                    'StockTransactions::show/$1',
                );
                $routes->options(
                    "stock-transactions/(:num)",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->get(
                    "stock-transactions/(:num)/details",
                    'StockTransactions::details/$1',
                );
                $routes->options(
                    "stock-transactions/(:num)/details",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->post(
                    "stock-transactions/(:num)/submit-revision",
                    'StockTransactions::submitRevision/$1',
                );
                $routes->post("stock-opnames", "StockOpnames::create");
                $routes->get("stock-opnames", "StockOpnames::index");
                $routes->put(
                    "stock-opnames/(:num)",
                    'StockOpnames::update/$1',
                );
                $routes->get(
                    "stock-opnames/(:num)",
                    'StockOpnames::show/$1',
                );
                $routes->post(
                    "stock-opnames/(:num)/submit",
                    'StockOpnames::submit/$1',
                );
                $routes->options(
                    "stock-transactions/(:num)/submit-revision",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->put(
                    "stock-transactions/(:num)",
                    'StockTransactions::updateDraft/$1',
                );
                $routes->post(
                    "stock-transactions/(:num)/submit",
                    'StockTransactions::submitDraft/$1',
                );
                $routes->options(
                    "stock-transactions/(:num)/submit",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->post(
                    "stock-transactions/(:num)/cancel",
                    'StockTransactions::cancelDraft/$1',
                );
                $routes->options(
                    "stock-transactions/(:num)/cancel",
                    static fn() => service("response")->setStatusCode(204),
                );
            },
            );

            $routes->group(
                "",
                ["filter" => "role:admin,dapur"],
                static function ($routes) {
                    // [MODULE: Menu & Nutrition Write Surface] Roles: admin, dapur | Controller: App\Controllers\Api\V1\Dishes, DishCompositions, Menus, MenuSchedules, SpkBasah, SpkKeringPengemas, SpkStockInPrefill
                    $routes->post("dishes", "Dishes::create");
                    $routes->put("dishes/(:num)", 'Dishes::update/$1');
                    $routes->patch(
                        "dishes/(:num)/deactivate",
                        'Dishes::deactivate/$1',
                    );
                    $routes->patch(
                        "dishes/(:num)/reactivate",
                        'Dishes::reactivate/$1',
                    );
                    $routes->delete("dishes/(:num)", 'Dishes::delete/$1');
                    $routes->post(
                        "dish-compositions",
                        "DishCompositions::create",
                    );
                    $routes->put(
                        "dish-compositions/(:num)",
                        'DishCompositions::update/$1',
                    );
                    $routes->delete(
                        "dish-compositions/(:num)",
                        'DishCompositions::delete/$1',
                    );
                    $routes->post("menu-dishes", "Menus::assignSlot");
                    $routes->put("menu-dishes/(:num)", 'Menus::updateSlot/$1');
                    $routes->delete(
                        "menu-dishes/(:num)",
                        'Menus::deleteSlot/$1',
                    );
                    $routes->post("menu-schedules", "MenuSchedules::create");
                    $routes->put(
                        "menu-schedules/(:num)",
                        'MenuSchedules::update/$1',
                    );
                },
            );

            $routes->group(
                "",
                ["filter" => "role:admin,gudang"],
                static function ($routes) {
                    // [MODULE: Daily Patients Write Surface] Roles: admin, gudang | Controller: App\Controllers\Api\V1\DailyPatients
                    $routes->post("daily-patients", "DailyPatients::create");
                    $routes->put(
                        "daily-patients/(:num)",
                        'DailyPatients::update/$1',
                    );
                },
            );

            $routes->group(
                "",
                ["filter" => "role:admin,dapur,gudang"],
                static function ($routes) {
                    // [MODULE: SPK Write & Helper Surface] Roles: admin, dapur, gudang | Controller: App\Controllers\Api\V1\SpkBasah, SpkKeringPengemas, SpkStockInPrefill
                    $routes->post("spk/basah/generate", "SpkBasah::generate");
                    $routes->post(
                        "spk/basah/operational-stock-preview",
                        "SpkBasah::operationalStockPreview",
                    );
                    $routes->post(
                        "spk/kering-pengemas/generate",
                        "SpkKeringPengemas::generate",
                    );
                    $routes->post(
                        "spk/basah/history/(:num)/override",
                        'SpkBasah::overrideItem/$1',
                    );
                    $routes->post(
                        "spk/kering-pengemas/history/(:num)/override",
                        'SpkKeringPengemas::overrideItem/$1',
                    );
                    $routes->get(
                        "spk/stock-in-prefill/(:num)",
                        'SpkStockInPrefill::show/$1',
                    );
                },
            );

            $routes->group(
                "",
                ["filter" => "role:admin,gudang"],
                static function ($routes) {
                    // [MODULE: SPK Stock Finalization Surface] Roles: admin, gudang | Controller: App\Controllers\Api\V1\SpkBasah, SpkKeringPengemas
                    $routes->post(
                        "spk/basah/history/(:num)/post-stock",
                        'SpkBasah::postStock/$1',
                    );
                    $routes->post(
                        "spk/kering-pengemas/history/(:num)/post-stock",
                        'SpkKeringPengemas::postStock/$1',
                    );
                },
            );

            $routes->group("", ["filter" => "role:admin"], static function ($routes, ) {
                // [MODULE: Admin Surface] Roles: admin | Controller: App\Controllers\Api\V1\Roles, ItemCategories, ItemUnits, StockTransactions, StockOpnames, SpkBasah, SpkKeringPengemas, Users, Items
                $routes->get("roles", "Roles::index");

                $routes->post("item-categories", "ItemCategories::create");
                $routes->put(
                    "item-categories/(:num)",
                    'ItemCategories::update/$1',
                );
                $routes->delete(
                    "item-categories/(:num)",
                    'ItemCategories::delete/$1',
                );
                $routes->patch(
                    "item-categories/(:num)/restore",
                    'ItemCategories::restore/$1',
                );

                $routes->post("item-units", "ItemUnits::create");
                $routes->put("item-units/(:num)", 'ItemUnits::update/$1');
                $routes->delete("item-units/(:num)", 'ItemUnits::delete/$1');
                $routes->patch(
                    "item-units/(:num)/restore",
                    'ItemUnits::restore/$1',
                );

                $routes->post(
                    "stock-transactions/direct-corrections",
                    "StockTransactions::directCorrection",
                );
                $routes->options(
                    "stock-transactions/direct-corrections",
                    static fn() => service("response")->setStatusCode(204),
                );

                $routes->post(
                    "stock-transactions/(:num)/approve",
                    'StockTransactions::approve/$1',
                );
                $routes->options(
                    "stock-transactions/(:num)/approve",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->post(
                    "stock-transactions/(:num)/reject",
                    'StockTransactions::reject/$1',
                );
                $routes->post(
                    "stock-opnames/(:num)/approve",
                    'StockOpnames::approve/$1',
                );
                $routes->post(
                    "stock-opnames/(:num)/reject",
                    'StockOpnames::reject/$1',
                );
                $routes->post(
                    "stock-opnames/(:num)/post",
                    'StockOpnames::post/$1',
                );
                $routes->options(
                    "stock-transactions/(:num)/reject",
                    static fn() => service("response")->setStatusCode(204),
                );
                // User management endpoints
                $routes->get("users", "Users::index");
                $routes->options(
                    "users",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->post("users", "Users::create");
                $routes->get("users/(:num)", 'Users::show/$1');
                $routes->options(
                    "users/(:num)",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->put("users/(:num)", 'Users::update/$1');
                $routes->patch("users/(:num)/activate", 'Users::activate/$1');
                $routes->options(
                    "users/(:num)/activate",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->patch(
                    "users/(:num)/deactivate",
                    'Users::deactivate/$1',
                );
                $routes->options(
                    "users/(:num)/deactivate",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->patch(
                    "users/(:num)/password",
                    'Users::changePassword/$1',
                );
                $routes->options(
                    "users/(:num)/password",
                    static fn() => service("response")->setStatusCode(204),
                );
                $routes->delete("users/(:num)", 'Users::delete/$1');
                $routes->delete("items/(:num)", 'Items::delete/$1');
                $routes->patch("items/(:num)/restore", 'Items::restore/$1');
                $routes->patch("users/(:num)/restore", 'Users::restore/$1');
            });
        });
    },
);

// Root
$routes->get("/", static function () {
    return response()
        ->setHeader("Content-Type", "text/html; charset=UTF-8")
        ->setBody(file_get_contents(FCPATH . "index.html"));
});

// All other paths
$routes->get("(:any)", static function (string $path) {
    $file = FCPATH . rtrim($path, "/") . "/index.html";

    return response()
        ->setHeader("Content-Type", "text/html; charset=UTF-8")
        ->setBody(
            file_get_contents(
                // Serve matching static page, or fall back to SPA root
                file_exists($file) ? $file : FCPATH . "index.html",
            ),
        );
});

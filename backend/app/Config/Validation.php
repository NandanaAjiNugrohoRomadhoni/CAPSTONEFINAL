<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var list<string>
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    // Rules: Base List Query Parameters
    // Shared validation rules for pagination, filtering, and sorting
    // --------------------------------------------------------------------

    /**
     * Validation group for Item list endpoint (GET /api/v1/items)
     * 
     * Supported query parameters:
     * - page: Current page number (1-based)
     * - perPage: Items per page (1-100)
     * - q / search: Free-text search on name
     * - sortBy: Field name to sort by (allowlisted in ItemListService)
     * - sortDir: Sort direction (ASC or DESC)
     * - item_category_id: Filter by item category
     * - is_active: Filter by active status (true/false)
     * - created_at_from: Filter items created on or after date (YYYY-MM-DD)
     * - created_at_to: Filter items created on or before date (YYYY-MM-DD)
     * - updated_at_from: Filter items updated on or after date (YYYY-MM-DD)
     * - updated_at_to: Filter items updated on or before date (YYYY-MM-DD)
     */
    public array $itemList = [
        'page'              => 'permit_empty|is_natural_no_zero|less_than_equal_to[999999]',
        'perPage'           => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]',
        'q'                 => 'permit_empty|string',
        'search'            => 'permit_empty|string',
        'sortBy'            => 'permit_empty|string|alpha_dash',
        'sortDir'           => 'permit_empty|in_list[ASC,DESC,asc,desc]',
        'item_category_id'  => 'permit_empty|is_natural_no_zero',
        'is_active'         => 'permit_empty|in_list[0,1,true,false]',
        'created_at_from'   => 'permit_empty|valid_date[Y-m-d]',
        'created_at_to'     => 'permit_empty|valid_date[Y-m-d]',
        'updated_at_from'   => 'permit_empty|valid_date[Y-m-d]',
        'updated_at_to'     => 'permit_empty|valid_date[Y-m-d]',
    ];

    public array $itemList_errors = [
        'page' => [
            'permit_empty'              => 'The page parameter must be a positive integer.',
            'is_natural_no_zero'        => 'The page parameter must be a positive integer.',
            'less_than_equal_to'        => 'The page parameter must not exceed 999999.',
        ],
        'perPage' => [
            'permit_empty'              => 'The perPage parameter must be a positive integer.',
            'is_natural_no_zero'        => 'The perPage parameter must be a positive integer.',
            'less_than_equal_to'        => 'The perPage parameter must not exceed 100.',
        ],
        'q' => [
            'permit_empty'              => 'The q parameter must be a string.',
            'string'                    => 'The q parameter must be a string.',
        ],
        'search' => [
            'permit_empty'              => 'The search parameter must be a string.',
            'string'                    => 'The search parameter must be a string.',
        ],
        'sortBy' => [
            'permit_empty'              => 'The sortBy parameter must be a valid field name.',
            'string'                    => 'The sortBy parameter must be a valid field name.',
            'alpha_dash'                => 'The sortBy parameter must contain only alphanumeric characters and underscores.',
        ],
        'sortDir' => [
            'permit_empty'              => 'The sortDir parameter must be either ASC or DESC.',
            'in_list'                   => 'The sortDir parameter must be either ASC or DESC.',
        ],
        'item_category_id' => [
            'permit_empty'              => 'The item_category_id parameter must be a positive integer.',
            'is_natural_no_zero'        => 'The item_category_id parameter must be a positive integer.',
        ],
        'is_active' => [
            'permit_empty'              => 'The is_active parameter must be true or false.',
            'in_list'                   => 'The is_active parameter must be true or false.',
        ],
        'created_at_from' => [
            'permit_empty'              => 'The created_at_from parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The created_at_from parameter must be a valid date (YYYY-MM-DD).',
        ],
        'created_at_to' => [
            'permit_empty'              => 'The created_at_to parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The created_at_to parameter must be a valid date (YYYY-MM-DD).',
        ],
        'updated_at_from' => [
            'permit_empty'              => 'The updated_at_from parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The updated_at_from parameter must be a valid date (YYYY-MM-DD).',
        ],
        'updated_at_to' => [
            'permit_empty'              => 'The updated_at_to parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The updated_at_to parameter must be a valid date (YYYY-MM-DD).',
        ],
    ];

    /**
     * Validation group for Stock Transaction list endpoint (GET /api/v1/stock-transactions)
     * 
     * Supported query parameters:
     * - page: Current page number (1-based)
     * - perPage: Items per page (1-100)
     * - q / search: Free-text search on spk_id
     * - sortBy: Field name to sort by (allowlisted in StockTransactionListService)
     * - sortDir: Sort direction (ASC or DESC)
     * - status_id: Filter by approval status
     * - type_id: Filter by transaction type
     * - transaction_date_from: Filter transactions on or after date (YYYY-MM-DD)
     * - transaction_date_to: Filter transactions on or before date (YYYY-MM-DD)
     * - created_at_from: Filter transactions created on or after date (YYYY-MM-DD)
     * - created_at_to: Filter transactions created on or before date (YYYY-MM-DD)
     * - updated_at_from: Filter transactions updated on or after date (YYYY-MM-DD)
     * - updated_at_to: Filter transactions updated on or before date (YYYY-MM-DD)
     */
    public array $stockTransactionList = [
        'page'              => 'permit_empty|is_natural_no_zero|less_than_equal_to[999999]',
        'perPage'           => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]',
        'q'                 => 'permit_empty|string',
        'search'            => 'permit_empty|string',
        'sortBy'            => 'permit_empty|string|alpha_dash',
        'sortDir'           => 'permit_empty|in_list[ASC,DESC,asc,desc]',
        'status_id'         => 'permit_empty|is_natural_no_zero',
        'type_id'           => 'permit_empty|is_natural_no_zero',
        'transaction_date_from' => 'permit_empty|valid_date[Y-m-d]',
        'transaction_date_to'   => 'permit_empty|valid_date[Y-m-d]',
        'created_at_from'   => 'permit_empty|valid_date[Y-m-d]',
        'created_at_to'     => 'permit_empty|valid_date[Y-m-d]',
        'updated_at_from'   => 'permit_empty|valid_date[Y-m-d]',
        'updated_at_to'     => 'permit_empty|valid_date[Y-m-d]',
    ];

    public array $stockTransactionList_errors = [
        'page' => [
            'permit_empty'              => 'The page parameter must be a positive integer.',
            'is_natural_no_zero'        => 'The page parameter must be a positive integer.',
            'less_than_equal_to'        => 'The page parameter must not exceed 999999.',
        ],
        'perPage' => [
            'permit_empty'              => 'The perPage parameter must be a positive integer.',
            'is_natural_no_zero'        => 'The perPage parameter must be a positive integer.',
            'less_than_equal_to'        => 'The perPage parameter must not exceed 100.',
        ],
        'q' => [
            'permit_empty'              => 'The q parameter must be a string.',
            'string'                    => 'The q parameter must be a string.',
        ],
        'search' => [
            'permit_empty'              => 'The search parameter must be a string.',
            'string'                    => 'The search parameter must be a string.',
        ],
        'sortBy' => [
            'permit_empty'              => 'The sortBy parameter must be a valid field name.',
            'string'                    => 'The sortBy parameter must be a valid field name.',
            'alpha_dash'                => 'The sortBy parameter must contain only alphanumeric characters and underscores.',
        ],
        'sortDir' => [
            'permit_empty'              => 'The sortDir parameter must be either ASC or DESC.',
            'in_list'                   => 'The sortDir parameter must be either ASC or DESC.',
        ],
        'status_id' => [
            'permit_empty'              => 'The status_id parameter must be a positive integer.',
            'is_natural_no_zero'        => 'The status_id parameter must be a positive integer.',
        ],
        'type_id' => [
            'permit_empty'              => 'The type_id parameter must be a positive integer.',
            'is_natural_no_zero'        => 'The type_id parameter must be a positive integer.',
        ],
        'transaction_date_from' => [
            'permit_empty'              => 'The transaction_date_from parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The transaction_date_from parameter must be a valid date (YYYY-MM-DD).',
        ],
        'transaction_date_to' => [
            'permit_empty'              => 'The transaction_date_to parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The transaction_date_to parameter must be a valid date (YYYY-MM-DD).',
        ],
        'created_at_from' => [
            'permit_empty'              => 'The created_at_from parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The created_at_from parameter must be a valid date (YYYY-MM-DD).',
        ],
        'created_at_to' => [
            'permit_empty'              => 'The created_at_to parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The created_at_to parameter must be a valid date (YYYY-MM-DD).',
        ],
        'updated_at_from' => [
            'permit_empty'              => 'The updated_at_from parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The updated_at_from parameter must be a valid date (YYYY-MM-DD).',
        ],
        'updated_at_to' => [
            'permit_empty'              => 'The updated_at_to parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The updated_at_to parameter must be a valid date (YYYY-MM-DD).',
        ],
    ];

    /**
     * Validation group for User list endpoint (GET /api/v1/users)
     * 
     * Supported query parameters:
     * - page: Current page number (1-based)
     * - perPage: Items per page (1-100)
     * - q / search: Free-text search on name, username, and email
     * - sortBy: Field name to sort by (allowlisted in UserListService)
     * - sortDir: Sort direction (ASC or DESC)
     * - role_id: Filter by role ID
     * - is_active: Filter by active status (true/false)
     * - created_at_from: Filter users created on or after date (YYYY-MM-DD)
     * - created_at_to: Filter users created on or before date (YYYY-MM-DD)
     * - updated_at_from: Filter users updated on or after date (YYYY-MM-DD)
     * - updated_at_to: Filter users updated on or before date (YYYY-MM-DD)
     */
    public array $userList = [
        'page'              => 'permit_empty|is_natural_no_zero|less_than_equal_to[999999]',
        'perPage'           => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]',
        'q'                 => 'permit_empty|string',
        'search'            => 'permit_empty|string',
        'sortBy'            => 'permit_empty|string|alpha_dash',
        'sortDir'           => 'permit_empty|in_list[ASC,DESC,asc,desc]',
        'role_id'           => 'permit_empty|is_natural_no_zero',
        'is_active'         => 'permit_empty|in_list[0,1,true,false]',
        'created_at_from'   => 'permit_empty|valid_date[Y-m-d]',
        'created_at_to'     => 'permit_empty|valid_date[Y-m-d]',
        'updated_at_from'   => 'permit_empty|valid_date[Y-m-d]',
        'updated_at_to'     => 'permit_empty|valid_date[Y-m-d]',
    ];

    public array $userList_errors = [
        'page' => [
            'permit_empty'              => 'The page parameter must be a positive integer.',
            'is_natural_no_zero'        => 'The page parameter must be a positive integer.',
            'less_than_equal_to'        => 'The page parameter must not exceed 999999.',
        ],
        'perPage' => [
            'permit_empty'              => 'The perPage parameter must be a positive integer.',
            'is_natural_no_zero'        => 'The perPage parameter must be a positive integer.',
            'less_than_equal_to'        => 'The perPage parameter must not exceed 100.',
        ],
        'q' => [
            'permit_empty'              => 'The q parameter must be a string.',
            'string'                    => 'The q parameter must be a string.',
        ],
        'search' => [
            'permit_empty'              => 'The search parameter must be a string.',
            'string'                    => 'The search parameter must be a string.',
        ],
        'sortBy' => [
            'permit_empty'              => 'The sortBy parameter must be a valid field name.',
            'string'                    => 'The sortBy parameter must be a valid field name.',
            'alpha_dash'                => 'The sortBy parameter must contain only alphanumeric characters and underscores.',
        ],
        'sortDir' => [
            'permit_empty'              => 'The sortDir parameter must be either ASC or DESC.',
            'in_list'                   => 'The sortDir parameter must be either ASC or DESC.',
        ],
        'role_id' => [
            'permit_empty'              => 'The role_id parameter must be a positive integer.',
            'is_natural_no_zero'        => 'The role_id parameter must be a positive integer.',
        ],
        'is_active' => [
            'permit_empty'              => 'The is_active parameter must be true or false.',
            'in_list'                   => 'The is_active parameter must be true or false.',
        ],
        'created_at_from' => [
            'permit_empty'              => 'The created_at_from parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The created_at_from parameter must be a valid date (YYYY-MM-DD).',
        ],
        'created_at_to' => [
            'permit_empty'              => 'The created_at_to parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The created_at_to parameter must be a valid date (YYYY-MM-DD).',
        ],
        'updated_at_from' => [
            'permit_empty'              => 'The updated_at_from parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The updated_at_from parameter must be a valid date (YYYY-MM-DD).',
        ],
        'updated_at_to' => [
            'permit_empty'              => 'The updated_at_to parameter must be a valid date (YYYY-MM-DD).',
            'valid_date'                => 'The updated_at_to parameter must be a valid date (YYYY-MM-DD).',
        ],
    ];
}

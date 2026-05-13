<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuModel extends Model
{
    protected $table         = 'menus';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'name'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';
    protected $useAutoIncrement = false;
}

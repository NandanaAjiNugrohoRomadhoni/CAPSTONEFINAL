<?php

namespace App\Models;

use CodeIgniter\Model;

class MealTimeModel extends Model
{
    protected $table         = 'meal_times';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['name'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';
}

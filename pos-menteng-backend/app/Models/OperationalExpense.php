<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OperationalExpense extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'amount',
        'expense_date',
        'recorded_by'
    ];

}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Transaction extends Model
{
    protected $table = 'transactions';
    use HasFactory;
    
    /**
     * The relationships that should always be loaded.
     * 
     * @var array
     */
    protected $with = ['category.parent', 'account'];
    
    protected $fillable = [
        'account_id',
        'category_id',
        'transaction_type',
        'amount',
        'description',
        'transaction_date',
        'payment_method',
        'reference_number',
        'tags',
        'location',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

}

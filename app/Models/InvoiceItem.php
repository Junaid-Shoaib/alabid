<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'hs_code',
        'uom',
        'description',
        'product_name',
        'invoice_id',
        'item_id',
        'unit_price',
        'quantity',
        'value_of_goods',
        'sale_tax_rate',
        'amount_of_saleTax',
        'extra_tax',
        'further_tax',
        'total',
    ];

    // Relationship: InvoiceItem belongs to Invoice
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // Relationship: InvoiceItem belongs to Item
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}

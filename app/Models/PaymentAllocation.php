<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سهم یک رسید که به یک بل رسیده است. */
#[Fillable(['payment_id', 'student_invoice_id', 'amount'])]
class PaymentAllocation extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class, 'student_invoice_id');
    }
}

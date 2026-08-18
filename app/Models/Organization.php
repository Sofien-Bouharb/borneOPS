<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Organization extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'type',
        'contact_email',
        'contact_phone',
        'address',
    ];
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}

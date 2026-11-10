<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function recordedLoginAt(): ?CarbonInterface
    {
        return $this->login_at ?? (str_ends_with($this->action, '.login.store') && $this->outcome === 'success' ? $this->created_at : null);
    }

    public function recordedLogoutAt(): ?CarbonInterface
    {
        return $this->logout_at ?? $this->session_logout_at ?? (str_ends_with($this->action, '.logout') && $this->outcome === 'success' ? $this->created_at : null);
    }

    public function approximateArea(): string
    {
        return $this->location_latitude === null || $this->location_longitude === null ? 'Not shared'
            : sprintf('Approx. %.1f, %.1f (rounded to 0.1 degree)', $this->location_latitude, $this->location_longitude);
    }

    protected function casts(): array
    {
        return ['targets' => 'array', 'details' => 'array', 'login_at' => 'datetime', 'logout_at' => 'datetime', 'session_logout_at' => 'datetime',
            'location_latitude' => 'decimal:1', 'location_longitude' => 'decimal:1', 'location_accuracy_km' => 'integer'];
    }
}

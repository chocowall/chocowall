<?php

namespace App\Models;

use App\Choco\NuGet\Package;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LdapRecord\Laravel\LdapImportable;
use LdapRecord\Laravel\ImportableFromLdap;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Group extends Authenticatable implements LdapImportable
{
    use ImportableFromLdap;

    /**
     * @return BelongsToMany
     */
    public function Packages(){
        return $this->belongsToMany(Package::class);
    }
}

<?php

namespace App\Console\Commands;

use App\Ldap\User;
use Illuminate\Console\Command;
use LdapRecord\Laravel\Import\Synchronizer;
use LdapRecord\Models\ActiveDirectory\Group as LdapGroup;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use App\Models\Group as EloquentGroup;

class DailyGroupSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:import:groups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {

        // $group = LdapGroup::findByAnr('Domain Users');

        $synchronizer = new Synchronizer(EloquentGroup::class, $config = [
            'sync_attributes' => [
                'name' => 'cn'
            ],
        ]);

        foreach ((new LdapGroup)->get() as $group) {
            $synchronizer->run($group)->save();
        }
    }
}

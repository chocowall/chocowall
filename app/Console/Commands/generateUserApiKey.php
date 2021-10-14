<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
//use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use App\Ldap\User as LdapUser;
use App\Models\User;
use App\Models\Apikey;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class generateUserApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:apikey {username}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a ApiKey ';

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

        $username = $this->argument('username');

        if (!LdapUser::findBy('samaccountname', $username))
        {
            return $this->error("User [{$username}] does not exist.");
        }

        $this->call('ldap:import', [
            'provider' => 'users',
            'user' => $username,
            '--no-interaction',
        ]);

        $userid = User::where('username', $username)->first()->id;

        if (Apikey::where('user_id', $userid)->exists()) {

            if ($this->confirm("User [{$username}] already has an apikey, would you like to replace it?")) {
                Apikey::where('user_id', $userid)
                    ->update(['apikey' => Str::orderedUuid()]);
                $apikey = apikey::where('user_id', $userid)
                    ->first()->apikey;
                return $this->info("Generated New API key is '$apikey'");
            } else {
                    return $this->info("Okay, no Apikey were update");

            }
        }

        $apikey = New Apikey();
        $apikey->user_id = $userid;
        $apikey->apikey = Str::orderedUuid();
        $apikey->save();

        return $this->info("\nGenerated API key is '$apikey->apikey'");
    }
}

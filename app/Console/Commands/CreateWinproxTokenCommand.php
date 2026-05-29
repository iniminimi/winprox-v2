<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateWinproxTokenCommand extends Command
{
    protected $signature = 'winprox:token {user : User ID or email} {--name=cli}';

    protected $description = 'Maak een Sanctum personal access token voor API /api/v1';

    public function handle(): int
    {
        $identifier = (string) $this->argument('user');
        $user = is_numeric($identifier)
            ? User::query()->find($identifier)
            : User::query()->where('email', $identifier)->first();

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $token = $user->createToken((string) $this->option('name'));

        $this->info('Token (bewaar veilig, wordt niet opnieuw getoond):');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}

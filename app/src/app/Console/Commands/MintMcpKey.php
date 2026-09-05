<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ApiKeys\MintApiKey;
use App\Domain\Auth\EmailNormalizer;
use App\Models\Admin;
use Illuminate\Console\Command;

/**
 * `mcp:key`: mints one MCP api key for an admin and prints it once
 * (docs/spec.md §5 "MCP endpoint", §6.1 `make mcp-key`). The database
 * keeps only the digest, so a key that scrolls off is a key to revoke and
 * mint again.
 */
final class MintMcpKey extends Command
{
    protected $signature = 'mcp:key {email : the admin the key belongs to} {--name=Claude Code : what the key is for, shown beside it}';

    protected $description = 'Mint an MCP api key for an admin and print it once';

    public function handle(MintApiKey $mintApiKey): int
    {
        $email = $this->argument('email');
        $name = $this->option('name');

        if (! is_string($email) || ! is_string($name) || $name === '') {
            $this->error('An admin email and a non-empty --name are required.');

            return self::FAILURE;
        }

        $admin = Admin::query()->where('email', EmailNormalizer::normalize($email))->first();

        if ($admin === null) {
            $this->error("No admin has the address {$email}.");

            return self::FAILURE;
        }

        $minted = $mintApiKey($admin, $name);

        $this->info("Minted {$minted->key->id} ({$name}) for {$admin->email}. The key is shown once:");
        $this->line($minted->plaintext);

        return self::SUCCESS;
    }
}

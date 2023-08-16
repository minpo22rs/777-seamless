<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameRound extends Model
{
    protected $fillable = [
        'provider',
        'round_id',
        'game_code',
        'game_name',
        'game_type',
        'player_id',
        'partner_id',
        'bet',
        'settle',
        'win_loss',
        'is_round_ended',
        'created_at',
        'updated_at',
    ];
}

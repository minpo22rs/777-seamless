<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WinLossPlayer extends Model
{
    protected $fillable = [
        'currency',
        'report_type',
        'provider_id',
        'provider_name',
        'game_id',
        'game_name',
        'game_type',
        'bet',
        'win',
        'loss',
        'tie',
        'player_id',
        'partner_id',
        'period',
    ];
}

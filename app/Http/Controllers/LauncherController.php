<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class LauncherController extends Controller
{

    public function joker(Request $request)
    {
        $lobbyURL = 'https://mm777bet.com';

        // Default THB
        $appID = "FB5T";

        // Find player
        $playerToken = isset($request->playerToken) ? $request->playerToken : null;
        $gameCode = isset($request->gameCode) ? $request->gameCode : null;

        $player = User::where('token', $playerToken)->first();

        if (!$player) {
            return response()->json(['message' => 'Player not found'], 400);
        }

        $playerCurrency = $player->currency;
        if ($playerCurrency == 'MMK') {
            $appID = "FB93";
        }

        $launcherURL = "https://www.gwc688.net/PlayGame?token=$playerToken&appid=$appID&gameCode=$gameCode&language=en&mobile=false&redirectUrl=$lobbyURL";
        return redirect($launcherURL);
    }
}

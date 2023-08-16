<?php

namespace App\Classes;

use App\Models\BetLog;
use App\Models\Constant;
use App\Models\GameRound;
use App\Models\User;
use App\Models\WinLossPlayer;
use Exception;
use Illuminate\Support\Facades\Log;

class Payment
{

    private $user;
    private $bet;
    private $type;

    static public function updateGameRound($payload)
    {
        try {
            $provider = isset($payload['provider']) ? $payload['provider'] : null;
            $round_id = isset($payload['round_id']) ? $payload['round_id'] : null;
            $game_code = isset($payload['game_code']) ? $payload['game_code'] : null;
            $game_name = isset($payload['game_name']) ? $payload['game_name'] : null;
            $game_type = isset($payload['game_type']) ? $payload['game_type'] : null;
            $partner_id = isset($payload['partner_id']) ? $payload['partner_id'] : null;
            $player_id = isset($payload['player_id']) ? $payload['player_id'] : null;
            $bet = isset($payload['bet']) ? $payload['bet'] : 0;
            $settle = isset($payload['settle']) ? $payload['settle'] : 0;
            $is_round_ended = isset($payload['is_round_ended']) ? $payload['is_round_ended'] : 0;
            // ** Update or create game round
            $initialData = [
                'provider' => $provider,
                'round_id' => $round_id,
                'game_code' => $game_code,
                'game_name' => $game_name,
                'game_type' => $game_type,
                'partner_id' => $partner_id,
                'player_id' => $player_id,
                'bet' => $bet,
                'settle' => $settle,
                'is_round_ended' => $is_round_ended
            ];
            Log::debug('initialData');
            Log::debug($initialData);
            $round = GameRound::firstOrCreate(
                [
                    'provider' => $provider,
                    'round_id' => $round_id,
                    'game_code' => $game_code
                ],
                $initialData
            );
            if (!$round->wasRecentlyCreated) {
                $round->bet += $bet;
                $round->settle += $settle;
                $round->is_round_ended = $is_round_ended;
                $round->save();
            }
            return $round;
        } catch (Exception $e) {
            Log::debug("updateGameRound===========>Error");
            Log::debug(['message' => $e->getMessage()]);
        }
    }

    static public function updatePlayerWinLossReport($payload)
    {
        // Default value
        $report_type = $payload['report_type'];
        $currency = isset($payload['currency']) ? $payload['currency'] : 'THB';
        $provider_id = isset($payload['provider_id']) ? $payload['provider_id'] : null;
        $provider_name = isset($payload['provider_name']) ? $payload['provider_name'] : null;
        $game_id = isset($payload['game_id']) ? $payload['game_id'] : null;
        $game_name = isset($payload['game_name']) ? $payload['game_name'] : null;
        $game_type = isset($payload['game_type']) ? $payload['game_type'] : null;
        $win = isset($payload['win']) ? $payload['win'] : 0;
        $loss = isset($payload['loss']) ? $payload['loss'] : 0;
        $tie = isset($payload['tie']) ? $payload['tie'] : 0;
        $cancel = isset($payload['cancel']) ? $payload['cancel'] : 0;
        $player_id = isset($payload['player_id']) ? $payload['player_id'] : null;
        $partner_id = isset($payload['partner_id']) ? $payload['partner_id'] : null;
        $period = date('Y-m-d H:00:00');
        if ($report_type == 'Daily') {
            $period = date('Y-m-d 00:00:00');
        }
        // Update win loss report
        $initialData = [
            'currency' => $currency,
            'report_type' => $report_type,
            'provider_id' => $provider_id,
            'provider_name' => $provider_name,
            'game_id' => $game_id,
            'game_name' => $game_name,
            'game_type' => $game_type,
            'win' => $win,
            'loss' => $loss,
            'tie' => $tie,
            'cancel' => $cancel,
            'player_id' => $player_id,
            'partner_id' => $partner_id,
            'period' => $period,
        ];
        $winLossPlayer = WinLossPlayer::firstOrCreate(
            [
                'currency' => $currency,
                'report_type' => $report_type,
                'player_id' => $player_id,
                'partner_id' => $partner_id,
                'period' => $period,
                'provider_name' => $provider_name,
                'game_id' => $game_id,
                'game_name' => $game_name,
                'game_type' => $game_type
            ],
            $initialData
        );
        if (!$winLossPlayer->wasRecentlyCreated) {
            $winLossPlayer->win += $win;
            $winLossPlayer->loss += $loss;
            $winLossPlayer->tie += $tie;
            $winLossPlayer->cancel += $cancel;
            $winLossPlayer->save();
        }
        // Return
        return $winLossPlayer;
    }

    public function saveLog($payload)
    {
        try {
            $log = new BetLog();
            $log->currency = isset($payload['currency']) ? $payload['currency'] : 'THB';
            $log->action = $payload['action'];
            $log->provider = $payload['provider'];
            $log->game_type = $payload['game_type'];
            $log->game_ref = $payload['game_ref'];
            $log->transaction_ref = $payload['transaction_ref'];
            $log->player_username = $payload['player_username'];
            $log->amount = $payload['amount'];
            $log->before_balance = isset($payload['before_balance']) ? $payload['before_balance'] : 0;
            $log->after_balance = isset($payload['after_balance']) ? $payload['after_balance'] : 0;
            $log->save();
        } catch (Exception $err) {
            // Do nothing;
        }
    }

    public function payAll($userId, $bet, $type = 'SLOT')
    {
        $this->user = User::find($userId);
        if ($this->user) {
            $this->bet = $bet;
            $this->type = strtolower($type);
            // $this->payAffiliate();
            // $this->payCommission();
            $this->incrementTurnover();
        }
        return "SUCCEED";
    }

    private function payAffiliate()
    {
        $user = $this->user;
        $bet = $this->bet;

        if ($user->invitor_token) {
            $invitor = User::where('token', $user->invitor_token)->first();
            if ($invitor) {

                $PERCENT_L1 = Constant::where('variable', 'PERCENT_AFF_L1')->first()->value;
                $PERCENT_L1 = json_decode($PERCENT_L1, true);
                $payment = $bet * $PERCENT_L1[$this->type] / 100;
                $invitor->increment('aff_wallet', $payment);

                if ($invitor->invitor_token) {
                    $children = User::where('token', $invitor->invitor_token)->first();
                    if ($children) {
                        $PERCENT_L2 = Constant::where('variable', 'PERCENT_AFF_L2')->first()->value;
                        $PERCENT_L2 = json_decode($PERCENT_L2, true);
                        $payment = $bet * $PERCENT_L2[$this->type] / 100;
                        $children->increment('aff_wallet', $payment);
                    }
                }
            }
        }
    }

    private function payCommission()
    {
        $user = $this->user;
        $bet = $this->bet;

        if (!$user->promotion_id) {
            $percent = Constant::where('variable', 'PERCENT_REFUND')->first()->value;
            $percent = json_decode($percent, true);
            $payment = $bet * $percent[$this->type] / 100;

            $user->increment('refund_wallet', $payment);
        }
    }

    private function incrementTurnover()
    {
        $user = $this->user;
        $bet = $this->bet;

        $user->increment('turnover', $bet);
    }
}

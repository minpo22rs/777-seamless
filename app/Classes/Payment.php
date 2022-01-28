<?php

namespace App\Classes;

use App\Models\BetLog;
use App\Models\Constant;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

class Payment
{

    private $user;
    private $bet;
    private $type;

    public function saveLog($payload)
    {
        try {
            $log = new BetLog();
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
        $this->bet = $bet;
        $this->type = strtolower($type);
        $this->payAffiliate();
        $this->payCommission();
        $this->incrementTurnover();
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

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExampleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function getBalance(Request $request, $name)
    {
        // return $name;
        Log::debug($request);
        $token = md5($name . 'cd2ba3e39bb143cd8bfd509000d56a50');
        return [
            'codeId' => 0,
            'token' => $token,
            'member' =>
            [
                'username' => 'username',
                'balance' => 1000
            ]
        ];
    }

    //
}

<?php
namespace Bank\App\Controllers;
use Bank\App\App;
use Bank\App\DB\FileBase;
use Bank\App\Message;

class TransactionController {
    
    public function new ($transaction) {
        $writer = new FileBase('transactions');
        return $writer->create($transaction);
    }


    public function createByUser($userID){
        $accounts = (new FileBase('accounts'))->showAll();
        $user = (new FileBase('users'))->show($userID);
        $sender = $user->firstname . ' ' . $user->lastname;
        $accounts = array_filter($accounts, fn($account) => $account['uid'] === $userID);
        return App::view('user/newtransaction', [
            'userID' => $userID,
            'sender' => $sender,
            'accounts' => $accounts
        ]);
    }

    public function storeByUser($userID, $request){ 
        $acc = $request['acc'] ?? '';
        $riban = $request['riban'] ?? '';
        $rname = $request['rname'] ?? '';
        $samount = ($request['amount']) ?? '';
        $acc = intval($acc);
        $samount = intval($samount);

        $account = (new FileBase('accounts'))->show($acc);
        if ($account->uid !== $userID){
            Message::get()->set('red', 'Account not found'); //Account does not belong to user
            App::redirect('user/accounts');
            die;
        }

        if ($account->amount < $samount){
            Message::get()->set('red', 'Insuficient account balance');
            App::redirect('user/accounts');
            die;
        }

        $bankCode = substr($riban, 4, 5);
        if ($bankCode === '99999'){
            $accounts = (new FileBase('accounts'))->showAll();
            $accounts = array_values(array_filter($accounts, fn($account) => ($account['iban'] ===  $riban))); 
            if (count($accounts) < 1) {
                Message::get()->set('red', 'Account not found in BIT Bank database');
                App::redirect('user/accounts');
                die;
            }       
        }

        //reduce account's ammount 
        $account->amount -= $samount;
        (new FileBase('accounts'))->update($acc, $account);


        // //check if recipient's account is in Our bank
        $to = 0;
        if ($bankCode === '99999'){

            $to = $accounts[0]['id'];
            $raccount = (new FileBase('accounts'))->show($to);
            $raccount->amount += $samount; 
            $writer = new FileBase('accounts');
            $writer->update($to, $raccount);
        }

        echo '-----------<br/>';
        echo $to . '<br/>';
        echo '<pre>';
        print_r($raccount);
        echo '</pre>';
        
        //log transaction
        $fromIBAN = $account->iban;
        $user = (new FileBase('users'))->show($userID);
        $sender = $user->firstname . ' ' . $user->lastname;
        $transaction = (object) [
            'time' => date('Y-m-d H:i:s'),
            'from' => $acc,
            'to' => $to,
            'toIBAN' => $riban,
            'fromIBAN' => $fromIBAN,
            'fromName' => $sender,
            'toName' => $rname,
            'amount' => $samount,
            'curr' => 'Eur'
        ];

        $writer = new fileBase('transactions');
        $writer->create($transaction);
        Message::get()->set('green', $samount . ' Successfully sent to ' . $riban . '.');
        App::redirect('user/accounts');
    }

    public function showAccSent($accountID){
        $reader =  new FileBase('transactions');
        $transactions = $reader->showAll();
        return array_filter($transactions, fn($trans) => $trans['from'] == $accountID );
    }

    public function showAccReceived($accountID){
        $reader =  new FileBase('transactions');
        $transactions = $reader->showAll();
        return array_filter($transactions, fn($trans) => $trans['to'] == $accountID );
    }

    public function viewLogs(){
        $transactions = (new FileBase('transactions'))->showAll();
        return App::view('transactions/all', [
            'transactions' => $transactions
        ]);
    }

    // public function viewUserLogs($userID){
    //     $transactions = (new FileBase('transactions'))->showAll();
    //     $transactionsFrom = array_filter($transactions, fn($transaction) => $transaction['from'] === $userID);
    //     return App::view('transactions/all', [
    //         'transactions' => $transactions
    //     ]);
    // }

 
}



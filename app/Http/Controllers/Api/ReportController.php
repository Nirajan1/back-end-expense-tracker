<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionReportResource;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class ReportController extends Controller
{
    // report based on type income or expense
    public function transaction(Request $request)
    {
        $userId = Auth::id();
        $type = $request->query('type');

        if (!in_array($type, ['income', 'expense'])) {
            return response()->json([
                'response' => '400',
                'message' =>  'Invalid transaction type. Allowed: income, expense.',
            ], 400);
        }

        $baseQuery = Transaction::where('user_id', $userId)
            ->where('transaction_type', $request->query('type'));

        $transaction = Transaction::where('user_id', $userId)
            ->where('transaction_type', $request->query('type'))
            ->with(['category', 'paymentMethod'])
            ->orderBy('transaction_date', 'desc')
            ->paginate(15);

        $totalAmount = (clone $baseQuery)->sum('transaction_amount');

        return response()->json([
            'type' => $request->query('type'),
            'total_amount' => $totalAmount,
            'data' =>  TransactionReportResource::collection($transaction),
        ]);
    }

    // summary of total income , expense and net balance
    public function summary()
    {
        $userId = Auth::id();

        $totalIncome = Transaction::where('user_id', $userId)
            ->where('transaction_type', 'income')

            ->sum('transaction_amount');


        $totalExpense = Transaction::where('user_id', $userId)
            ->where('transaction_type', 'expense')

            ->sum('transaction_amount');

        return response()->json([
            'income_total' => $totalIncome,
            'expense_total' => $totalExpense,
            'net_balance' => $totalIncome - $totalExpense,
        ]);
    }
    // monthly summary
    public function monthlySummary()
    {
        $userId = Auth::id();

        $data = Transaction::where('user_id', $userId)

            ->selectRaw(
                '
                YEAR(transaction_date) as year,
                MONTH(transaction_date) as month,
                SUM(CASE WHEN transaction_type = "income" THEN transaction_amount ELSE 0 END) as income_total,
                SUM(CASE WHEN transaction_type = "expense" THEN transaction_amount ELSE 0 END) as expense_total
                '
            )
            ->groupByRaw('YEAR(transaction_date), MONTH(transaction_date)')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($row) {
                $monthName = Carbon::create()
                    ->month($row->month)
                    ->format('F');

                return [
                    'year' => $row->year,
                    'month' => $row->month,
                    'month_name' => $monthName,
                    'period' => $row->year . '-' . str_pad($row->month, 2, '0', STR_PAD_LEFT), // YYYY-MM format
                    'income_total' => (float) $row->income_total,
                    'expense_total' => (float) $row->expense_total,
                    'net_balance' => (float) ($row->income_total - $row->expense_total),
                ];
            });

        return response()->json([
            'data' => $data,
        ]);
    }
    //category wise
    public function categoryExpense()
    {
        $userId = Auth::id();

        $data = Transaction::where('user_id', $userId)
            ->where('transaction_type', 'expense')
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw('
            categories.id as category_id,
            categories.name as category_name,
            SUM(transactions.transaction_amount) as total_expense
        ')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_expense')
            ->get()
            ->map(fn($data) => [
                'category_id' => $data->category_id,
                'category_name' => $data->category_name,
                'total_expense' =>  (float) $data->total_expense,
            ]);


        return response()->json([
            'data' => $data,
        ]);
    }

    //expenseByPaymentMethod

    public function expenseByPaymentMethod()
    {
        $userId = Auth::id();

        $data = Transaction::where('user_id', $userId)
            ->where('transaction_type', 'expense')
            ->join('payment_methods', 'payment_methods.id', '=', 'transactions.payment_method_id')
            ->selectRaw('
            payment_methods.id as payment_method_id,
            payment_methods.name as payment_method_name,
            SUM(transactions.transaction_amount) as total_expense
        ')->groupBy('payment_methods.id', 'payment_methods.name')
            ->get()
            ->map(fn($row) => [
                'payment_method_id' => $row->payment_method_id,
                'payment_method_name' => $row->payment_method_name,
                'total_expense' => (float) $row->total_expense,
            ]);

        return response()->json([
            'data' => $data,
        ]);
    }
}

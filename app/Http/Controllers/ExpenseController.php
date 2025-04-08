<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses.
     */
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => Expense::all()
        ]);
    }

    /**
     * Store a newly created expense.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:expenses'
        ]);

        $expense = Expense::create($validated);

        return response()->json([
            'status' => 'success',
            'data' => $expense,
            'message' => 'Expense created successfully'
        ], 201);
    }

    /**
     * Display the specified expense.
     */
    public function show(Expense $expense)
    {
        return response()->json([
            'status' => 'success',
            'data' => $expense
        ]);
    }

    /**
     * Update the specified expense.
     */
    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:expenses,name,' . $expense->id
        ]);

        $expense->update($validated);

        return response()->json([
            'status' => 'success',
            'data' => $expense,
            'message' => 'Expense updated successfully'
        ]);
    }

    /**
     * Remove the specified expense.
     */
    public function destroy(Expense $expense)
    {
        $expense->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Expense deleted successfully'
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Purpose;
use Illuminate\Http\Request;

class PurposeController extends Controller
{
    /**
     * Display a listing of purposes.
     */
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => Purpose::all()
        ]);
    }

    /**
     * Store a newly created purpose.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:purposes'
        ]);

        $purpose = Purpose::create($validated);

        return response()->json([
            'status' => 'success',
            'data' => $purpose,
            'message' => 'Purpose created successfully'
        ], 201);
    }

    /**
     * Display the specified purpose.
     */
    public function show(Purpose $purpose)
    {
        return response()->json([
            'status' => 'success',
            'data' => $purpose
        ]);
    }

    /**
     * Update the specified purpose.
     */
    public function update(Request $request, Purpose $purpose)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:purposes,name,' . $purpose->id
        ]);

        $purpose->update($validated);

        return response()->json([
            'status' => 'success',
            'data' => $purpose,
            'message' => 'Purpose updated successfully'
        ]);
    }

    /**
     * Remove the specified purpose.
     */
    public function destroy(Purpose $purpose)
    {
        $purpose->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Purpose deleted successfully'
        ]);
    }
}
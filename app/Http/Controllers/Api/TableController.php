<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TableController extends Controller
{
    public function index()
    {
        $tables = Cache::remember('tables:all', 3600, function () {
            return Table::all();
        });
        return response()->json($tables);
    }

    public function store(Request $request)
    {
        $request->validate([
            'table_number' => 'required|string|unique:tables,table_number',
            'capacity' => 'required|integer|min:1',
            'status' => 'in:available,occupied,reserved',
        ]);

        $table = Table::create($request->all());

        Cache::forget('tables:all');

        return response()->json($table, 201);
    }

    public function show(Table $table)
    {
        return response()->json($table);
    }

    public function update(Request $request, Table $table)
    {
        $request->validate([
            'table_number' => 'string|unique:tables,table_number,' .  $table->id,
            'capacity' => 'integer|min:1',
            'status' => 'in:available,occupied,reserved',
        ]);

        $table->update($request->all());

        Cache::forget('tables:all');

        return response()->json($table);
    }

    public function destroy(Table $table)
    {
        $table->delete();

        Cache::forget('tables:all');

        return response()->json(['message' => 'Table deleted successfully']);
    }
}

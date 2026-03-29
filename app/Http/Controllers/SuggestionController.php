<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    public function getPriceSuggestion(Request $request)
    {
        $request->validate([
            'category' => 'required|string',
        ]);

        // Mock logic for AI/Algorithm price suggestion
        $category = strtolower($request->category);
        $suggestedPrice = 0;
        
        if (str_contains($category, 'tomato')) {
            $suggestedPrice = 500;
        } elseif (str_contains($category, 'corn') || str_contains($category, 'maize')) {
            $suggestedPrice = 150;
        } elseif (str_contains($category, 'cassava')) {
            $suggestedPrice = 1000;
        } else {
            $suggestedPrice = rand(5, 50) * 100; // Random between 500 and 5000 XAF
        }

        return response()->json([
            'category' => $category,
            'suggested_price' => $suggestedPrice,
            'currency' => 'XAF',
            'message' => 'Based on current market trends in your region.'
        ]);
    }

    public function getDescriptionSuggestion(Request $request)
    {
        $request->validate([
            'category' => 'required|string',
        ]);

        $category = ucfirst($request->category);
        
        $suggestions = [
            "Freshly harvested $category from our local farm, completely organic and ready for delivery.",
            "High-quality $category available in bulk quantities. Perfect for resellers or large families.",
            "Hand-picked $category. Farm-fresh, pesticide-free, and carefully packaged.",
        ];

        return response()->json([
            'category' => $category,
            'suggestions' => $suggestions
        ]);
    }
}

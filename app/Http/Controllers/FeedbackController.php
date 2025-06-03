<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Feedbacks;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    //Display the feedbacks that belong to target
    public function index(int $id){

    }

    // Display all the feedbacks that belong to a user
    public function index_user()
    {
        $userId = Auth::id();
    }

    // return the view for to the user to create a feedback
    public function create(){

    } 

    // Display the view to the user to edit his feedback
    public function edit(int $feedback_id)
    {

    }
    // display a specific feedback 
    public function show(int $feedback_id_id)
    {
    }


    public function store(Request $request)
    {
        $validate = $request->validate([
            'target_id' => 'integer|exists:users,id',
            'event_id' => 'integer|exists:event,id',
            'zone_id' => 'integer|exists:zone,id',
            'materielle_id' => 'integer|exists:materielles,id',
            'contenu' => 'string',
            'note' => 'required|numeric|min:0|max:5',
        ]);

        $userId = Auth::id();

        try{
            $feedbacks = Feedbacks::create([
                'user_id' => $userId,
                'target_id' => $request->target_id,
                'event_id' => $request->event_id,
                'zone_id' => $request->zone_id,
                'materielle_id' => $request->materielle_id,
                'contenu' => $request->contenu,
                'note' => $request->note
            ]);
            DB::commit();
            return response()->json([
                'message' => 'feedback added successfully.',
                'materielle' => $feedbacks,
            ], 201);
        }catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'message' => 'Error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validate = $request->validate([
            'note' => 'required|numeric|min:0|max:5',
            'contenu' => 'string',
        ]);
        $userId = Auth::id();

        $feedback = Feedbacks::findOrFail($id);
        if ($feedback->user_id != $userId) {
            return response()->json([
                'message' => 'prohibited action: you are not the owner',
            ], 500);
        }
    
        $feedback->update($request->all());
    
        return response()->json(['message' => 'Feedback updated successfully']);
    }

    public function destroy($id)
    {
        $feedback = Feedbacks::findOrFail($id);
        $feedback->delete();
        return response()->json(['message' => 'feedback deleted successfully']);

    }
    
}

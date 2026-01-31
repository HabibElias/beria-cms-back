<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberCreateRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $perPage = $request->query('perPage', 10);
        $page = $request->query('page', 1);

        return User::with('checkouts')->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MemberCreateRequest $request)
    {
        try {
            $attr = $request->validate(
                [
                    'name' => 'required|min:3',
                    'email' => 'required|email',
                    'phone' => 'required|min:10',
                    'role' => 'required|in:admin,librarian,user'
                ]
            );

            // Proceed with storing book
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        // check if the user already exists
        if (User::where('email', $attr['email'])->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'User already exists with that email',
            ], 409);
        }


        $attr = array_merge($attr, ['password' => password_hash('12341234', PASSWORD_BCRYPT)]);


        $member = User::create($attr);

        return response()->json(
            [
                'status' => true,
                'message' => 'Member created',
                'data' => $member
            ],
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(User $member)
    {
        //
        if ($member) {
            return response()->json(['status' => true, 'data' => $member]);
        } else
            return response()->json(['status' => false, 'message' => 'no user with this id'], 400);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $member)
    {
        // Prevent logged-in user from editing their own info here
        if ($request->user()->id === $member->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot edit your own information here.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($member->id),
            ],
            'phone' => 'required|string|min:10',
            'role' => 'required|in:admin,librarian,user',
        ], [
            'email.unique' => 'This email address is already in use by another member.',
        ]);

        $member->update($validated);
        return response()->json([
            'status' => true,
            'message' => 'Member updated',
            'data' => $member
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $member)
    {
        //
        if ($member) {
            $member->delete();
            return response()->json(['status' => true, 'message' => 'user deleted'], 200);
        } else
            return response()->json(['status' => false, 'message' => 'no user with this id'], 400);
    }
}

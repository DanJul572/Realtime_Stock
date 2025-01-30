<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoredUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $order = $request->input('order');
        $orderBy = $request->input('orderBy');
        $quickFilter = $request->input('quickFilter');

        return User::select('id', 'name', 'email', 'role_id')
            ->whereLike('name', '%' . $quickFilter . '%')
            ->orWhereLike('email', '%' . $quickFilter . '%')
            ->orWhereLike('role_id', '%' . $quickFilter . '%')
            ->orderBy($orderBy, $order)
            ->paginate(10);
    }

    public function store(StoredUserRequest $request)
    {
        $requestData = $request->validated();
        $requestData['password'] = bcrypt($requestData['password']);

        return User::create($requestData);
    }

    public function show(User $user)
    {
        return $user;
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $requestData = $request->validated();

        if (!empty($requestData['password'])) {
            $requestData['password'] = bcrypt($requestData['password']);
        } else {
            unset($requestData['password']);
        }

        $user->update($requestData);

        return $user->fresh();
    }


    public function destroy(User $user)
    {
        $user->delete();
        return 'User has been deleted.';
    }
}

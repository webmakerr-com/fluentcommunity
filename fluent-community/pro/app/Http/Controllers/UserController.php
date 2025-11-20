<?php

namespace FluentCommunityPro\App\Http\Controllers;

use FluentCommunityPro\App\Models\User;

class UserController extends Controller
{
	public function users()
	{
		return User::all();
	}
}
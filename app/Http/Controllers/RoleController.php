<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRequest;
use App\Http\Requests\Role\UpdateRequest;
use App\Models\AssignPermission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class RoleController extends Controller
{
    public function index(Role $model, Request $request)
    {
        //get roles for employee
        if ($request->role_type) {
            $model->Where("role_type", $request->role_type);
        }

        return $model->where("name", "!=", "company")->where('company_id', $request->company_id)->paginate($request->per_page);
    }

    public function store(StoreRequest $request)
    {
        try {
            $data = $request->validated();
            if ($request->company_id) {
                $data['company_id'] = $request->company_id;
            }
            $record = Role::create($data);

            if ($record) {
                return $this->response('Role Successfully created.', $record, true);
            } else {
                return $this->response('Role cannot create.', null, false);
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function update(UpdateRequest $request, Role $Role)
    {

        try {
            $data = $request->validated();

            if ($data) {

                $isNameExist = Role::where('name', $request->name)
                    ->where('company_id', $request->company_id)
                    ->first();

                if ($isNameExist) {
                    if ($isNameExist->id != $Role->id) {
                        return $this->response($request->room_no . ' Room Details are already Exist', null, false);
                    }
                }
                $record = $Role->update($request->all());
                return $this->response('Role successfully updated.', $record, true);
            } else {
                return $this->response('Role cannot update.', null, false);
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function destroy($id)
    {
        $role = Role::find($id);

        if ($role) {
            $role->delete();
            AssignPermission::where('role_id', $role->id)->delete();

            return $this->response('Role and Permissions successfully deleted.', $role, true);
        } else {
            return $this->response('Role cannot delete.', null, false);
        }
    }

    public function roles($id)
    {
        $record = User::with('roles')->find($id);
        return $this->response(null, $record, true, 200);
    }

    public function search(Role $model, Request $request, $key)
    {
        $model = $this->FilterCompanyList($model, $request);

        $fields = [
            'name',
        ];

        $model = $this->process_search($model, $key, $fields);

        return $model->paginate($request->per_page);
    }

    public function assignPermission(Request $request, $id)
    {
        if (is_array($request->role_id)) {
            foreach ($request->role_id as $role_id) {
                $record = Role::findById($role_id);
                $record->syncPermissions($request->permissions);
            }
        } else {
            $record = Role::findById($id);
            $record->syncPermissions($request->permissions);
        }

        return response()->json(204);
    }

    public function getPermission($id)
    {
        $record = Role::with('permissions')->find($id);
        return $this->response(null, $record, true);
    }

    public function deleteSelected(Request $request)
    {
        $record = Role::whereIn('id', $request->ids)->delete();

        if ($record) {
            return $this->response('Role Successfully Deleted.', $record, true);
        } else {
            return $this->response('Role cannot Deleted.', null, false);
        }
    }
}

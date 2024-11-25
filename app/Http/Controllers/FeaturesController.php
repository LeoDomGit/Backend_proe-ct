<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeatureRequest;
use App\Models\Features;
use App\Traits\HasCrud;
use Illuminate\Http\Request;

class FeaturesController extends Controller
{
    use HasCrud;

    public function __construct()
    {
        $this->model=Features::class;
        $this->view='Features/Index';
        $this->data=['features'=>$this->model::all()];

    }

    // This will use the UserRequest for validation
    public function update(FeatureRequest $request, $id)
    {
       $data=$request->validated();
       $data['updated_at']=now();
        $this->model::find($id)->update($data);
        $data=$this->model::all();
        return response()->json(['check'=>true,'data'=>$data]);
    }
}
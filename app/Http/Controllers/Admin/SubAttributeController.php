<?php

namespace App\Http\Controllers\Admin;

use App\CPU\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Attribute;
use Illuminate\Http\Request;
use App\Model\Translation;
use Brian2694\Toastr\Facades\Toastr;

class SubAttributeController extends Controller
{
    public function index(Request $request)
    {
        $query_param = [];
        $search = $request['search'];

        if ($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $attributes = Attribute::where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->Where('name', 'like', "%{$value}%");
                }
            });

            $query_param = ['search' => $request['search']];
        }else{
            $attributes =Attribute::whereNotNull(['Parent_Id']);
        }
        $attributes = $attributes->latest()->paginate(Helpers::pagination_limit())->appends($query_param);
        return view('admin-views.attribute.sub-attribute-view',compact('attributes','search'));
    }

    public function store(Request $request)
    {
        $attribute = new Attribute;
        $attribute->name = $request->name[array_search('en', $request->lang)];
        $attribute->Parent_Id = $request->par_id;
        $attribute->save();
        foreach ($request->lang as $index => $key) {
            if ($request->name[$index] && $key != 'en') {
                Translation::updateOrInsert(
                    ['translationable_type' => 'App\Model\Attribute',
                        'translationable_id' => $attribute->id,
                        'locale' => $key,
                        'key' => 'name'],
                    ['value' => $request->name[$index]]
                );
            }
        }
        Toastr::success('Attribute added successfully!');
        return back();
    }

    public function edit($id)
    {
        $attribute = Attribute::withoutGlobalScope('translate')->where('id', $id)->first();
        return view('admin-views.attribute.edit', compact('attribute'));
    }

    public function update(Request $request)
    {
        $attribute = Attribute::find($request->id);
        $attribute->name = $request->name[array_search('en', $request->lang)];
        $attribute->save();

        foreach ($request->lang as $index => $key) {
            if ($request->name[$index] && $key != 'en') {
                Translation::updateOrInsert(
                    ['translationable_type' => 'App\Model\Attribute',
                        'translationable_id' => $attribute->id,
                        'locale' => $key,
                        'key' => 'name'],
                    ['value' => $request->name[$index]]
                );
            }
        }
        Toastr::success('Attribute updated successfully!');
        return back();
    }

    public function delete(Request $request)
    {
        $translation = Translation::where('translationable_type','App\Model\Attribute')
                                    ->where('translationable_id',$request->id);
        $translation->delete();
        Attribute::destroy($request->id);
        return response()->json();
    }

    public function getSubAttribute(Request $request)
    {
        $data = Attribute::where("Parent_Id",$request->Id)->get();
        $output="";
        foreach($data as $row)
        {
            $output .= '<option data-role="tagsinput" value="'.$row->id.'">'.$row->name.'</option>';
        }
        echo $output;
    }

    public function fetch(Request $request)
    {
        if ($request->ajax()) {
            $data = Attribute::orderBy('id', 'desc')->get();
            return response()->json($data);
        }
        return null;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\CPU\BackEndHelper;
use App\CPU\Helpers;
use App\CPU\ImageManager;
use App\Http\Controllers\BaseController;
use App\Model\Brand;
use App\Model\Category;
use App\Model\Color;
use App\Model\DealOfTheDay;
use App\Model\FlashDealProduct;
use App\Model\Product;
use App\Model\Review;
use App\Model\Translation;
use App\Model\Wishlist;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;
use function App\CPU\translate;
use App\Model\Cart;

class ProductController extends BaseController
{
    public function add_new()
    {
        $cat = Category::where(['parent_id' => 0])->get();
        $br = Brand::orderBY('name', 'ASC')->get();
        return view('admin-views.product.add-new', compact('cat', 'br'));
    }

    public function featured_status(Request $request)
    {
        $product = Product::find($request->id);
        $product->featured = ($product['featured'] == 0 || $product['featured'] == null) ? 1 : 0;
        $product->save();
        $data = $request->status;
        return response()->json($data);
    }

    public function approve_status(Request $request)
    {
        $product = Product::find($request->id);
        $product->request_status = ($product['request_status'] == 0) ? 1 : 0;
        $product->save();

        return redirect()->route('admin.product.list', ['seller', 'status' => $product['request_status']]);
    }

    public function deny(Request $request)
    {
        $product = Product::find($request->id);
        $product->request_status = 2;
        $product->denied_note = $request->denied_note;
        $product->save();

        return redirect()->route('admin.product.list', ['seller', 'status' => 2]);
    }

    public function view($id)
    {
        $product = Product::with(['reviews'])->where(['id' => $id])->first();
        $reviews = Review::where(['product_id' => $id])->paginate(Helpers::pagination_limit());
        return view('admin-views.product.view', compact('product', 'reviews'));
    }

    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name'              => 'required',
            'desc'              =>'required',
            'category_id'       => 'required',
            'brand_id'          => 'required',
            'unit'              => 'required',
            //'images'            => 'required',
            //'image'             => 'required',
            'tax'               => 'required|min:0',
            'unit_price'        => 'required|numeric|min:1',
            'purchase_price'    => 'required|numeric|min:1',
            'discount'          => 'required|gt:-1',
            'shipping_cost'     => 'required|gt:-1',
            'code'              => 'required|numeric|min:1|digits_between:6,20|unique:products',
            'minimum_order_qty' => 'required|numeric|min:1',
        ], [
            //'images.required'       => 'Product images is required!',
            //'image.required'        => 'Product thumbnail is required!',
            'category_id.required'  => 'category  is required!',
            'brand_id.required'     => 'brand  is required!',
            'unit.required'         => 'Unit  is required!',
            'code.min'              => 'The code must be positive!',
            'code.digits_between'   => 'The code must be minimum 6 digits!',
            'minimum_order_qty.required' => 'The minimum order quantity is required!',
            'minimum_order_qty.min' => 'The minimum order quantity must be positive!',
        ]);

        if ($request['discount_type'] == 'percent') {
            $dis = ($request['unit_price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($request['unit_price'] <= $dis) {
            $validator->after(function ($validator) {
                $validator->errors()->add(
                    'unit_price', 'Discount can not be more or equal to the price!'
                );
            });
        }


        if (is_null($request->name[array_search('en', $request->lang)])) {
            $validator->after(function ($validator) {
                $validator->errors()->add(
                    'name', 'Name field is required!'
                );
            });
        }

        if (is_null($request->desc[array_search('en', $request->lang)])) {
            $validator->after(function ($validator) {
                $validator->errors()->add(
                    'desc', 'desc field is required!'
                );
            });
        }




        $category = [];

        if ($request->category_id != null) {
            $category[] = [
                'id' => $request->category_id,
                'position' => 1,
            ];
        }
        if ($request->sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_category_id,
                'position' => 2,
            ];
        }
        if ($request->sub_sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_sub_category_id,
                'position' => 3,
            ];
        }


        $choice_options = [];
        if ($request->has('choice')) {
            foreach ($request->choice_no as $key => $no) {
                $str = 'choice_options_' . $no;
                $item['name'] = 'choice_' . $no;
                $item['title'] = $request->choice[$key];
                $item['options'] = $request[$str];
                array_push($choice_options, $item);
            }
        }

        //combinations start
        $options = [];
        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
            $colors_active = 1;
            array_push($options, $request->colors);
        }


        $combinations = Helpers::combinations($options);

        $variations = [];
        $imagesss=[];
        $colorCode = [];
        $stock_count = 0;
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $item) {
                    if ($k > 0) {
                        $str .= '-' . str_replace(' ', '', $item);
                    } else {
                        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
                            $color_name = Color::where('code', $item)->first()->name;
                            $str .= $color_name;
                        } else {
                            $str .= str_replace(' ', '', $item);
                        }
                    }
                    array_push($colorCode, $item);
                }
                $item = [];
                $item['type'] = $str;
                $item['price'] = BackEndHelper::currency_to_usd(abs($request['price_' . str_replace('.', '_', $str)]));
                $item['sku'] = $request['sku_' . str_replace('.', '_', $str)];
                $item['qty'] = abs($request['qty_' . str_replace('.', '_', $str)]);
                array_push($variations, $item);
                $stock_count += $item['qty'];

                $x= $request->file('imageiiii_' . str_replace('.', '_', $str));
               // foreach ($x as $imagewww)
                //{
                    $temp_images=ImageManager::upload('product/', 'webp',  $x);
                //}
                array_push($imagesss, $temp_images);
            }
        } else {
            $stock_count = (integer)$request['current_stock'];
        }

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        //combinations end

        if ($request->ajax()) {
            return response()->json([], 200);
        } else {
            if ($request->file('images')) {
                foreach ($request->file('images') as $img) {
                    $product_images[] = ImageManager::upload('product/', 'webp', $img);
                }
                $images = json_encode($product_images);
            }


            for ($i=0;$i<count($variations);$i++) {
                $variationww = [];
                $colorww = [];
                array_push($colorww, $colorCode[$i]);
                array_push($variationww, $variations[$i]);

                $data[] =
                    array(
                        'user_id' => auth('admin')->id(),
                        'added_by' => "admin",
                        'name' => "admin",
                        'desc' => $request->desc[array_search('en', $request->lang)],
                        'code' => $color_name.'_'.$request->code,
                        'slug' => Str::slug($request->name[array_search('en', $request->lang)], '-') . '-' . Str::random(6),
                        'category_ids' => json_encode($category),
                        'brand_id' => $request->brand_id,
                        'unit' => $request->unit,
                        'details' => $request->description[array_search('en', $request->lang)],
                        'choice_options' => json_encode($choice_options),
                        'colors' => json_encode($colorww),
                        'variation' => json_encode($variationww),
                        'unit_price' => BackEndHelper::currency_to_usd($request->unit_price),
                        'purchase_price' => BackEndHelper::currency_to_usd($request->purchase_price),
                        'tax' => $request->tax_type == 'flat' ? BackEndHelper::currency_to_usd($request->tax) : $request->tax,
                        'tax_type' => $request->tax_type,
                        'discount' => $request->discount_type == 'flat' ? BackEndHelper::currency_to_usd($request->discount) : $request->discount,
                        'discount_type' => $request->discount_type,
                        'attributes' => json_encode($request->choice_attributes),
                        'current_stock' => abs($stock_count),
                        'minimum_order_qty' => $request->minimum_order_qty,
                        'video_provider' => 'youtube',
                        'video_url' => $request->video_link,
                        'request_status' => 1,
                        'shipping_cost' => BackEndHelper::currency_to_usd($request->shipping_cost),
                        'multiply_qty' => $request->multiplyQTY == 'on' ? 1 : 0,
                        'images' =>  json_encode($imagesss),
                        'thumbnail' => ImageManager::upload('product/thumbnail/', 'webp', $request->image),
                        'meta_title' => $request->meta_title,
                        'meta_description' => $request->meta_description,
                        'meta_image' => ImageManager::upload('product/meta/', 'webp', $request->meta_image),
                    );
            }

            foreach ($data as $inf) {
                DB::table('products')->insert($inf);

                $id = DB::getPdo()->lastInsertId();
                $data = [];
                foreach ($request->lang as $index => $key) {
                    if ($request->name[$index] && $key != 'en') {
                        $data[] = array(
                            'translationable_type' => 'App\Model\Product',
                            'translationable_id' => $id,
                            'locale' => $key,
                            'key' => 'name',
                            'value' => $request->name[$index],
                        );
                    }
                    if ($request->description[$index] && $key != 'en') {
                        $data[] = array(
                            'translationable_type' => 'App\Model\Product',
                            'translationable_id' => $id,
                            'locale' => $key,
                            'key' => 'description',
                            'value' => $request->description[$index],
                        );
                    }
                }

                Translation::insert($data);
            }
            Toastr::success(translate('Product added successfully!'));
            return redirect()->route('admin.product.list', ['in_house']);
        }

//        $validator = Validator::make($request->all(), [
//            'name'              => 'required',
//            'desc'              =>'required',
//            'category_id'       => 'required',
//            'brand_id'          => 'required',
//            'unit'              => 'required',
//            'images'            => 'required',
//            'image'             => 'required',
//            'tax'               => 'required|min:0',
//            'unit_price'        => 'required|numeric|min:1',
//            'purchase_price'    => 'required|numeric|min:1',
//            'discount'          => 'required|gt:-1',
//            'shipping_cost'     => 'required|gt:-1',
//            'code'              => 'required|numeric|min:1|digits_between:6,20|unique:products',
//            'minimum_order_qty' => 'required|numeric|min:1',
//        ], [
//            'images.required'       => 'Product images is required!',
//            'image.required'        => 'Product thumbnail is required!',
//            'category_id.required'  => 'category  is required!',
//            'brand_id.required'     => 'brand  is required!',
//            'unit.required'         => 'Unit  is required!',
//            'code.min'              => 'The code must be positive!',
//            'code.digits_between'   => 'The code must be minimum 6 digits!',
//            'minimum_order_qty.required' => 'The minimum order quantity is required!',
//            'minimum_order_qty.min' => 'The minimum order quantity must be positive!',
//        ]);
//
//        if ($request['discount_type'] == 'percent') {
//            $dis = ($request['unit_price'] / 100) * $request['discount'];
//        } else {
//            $dis = $request['discount'];
//        }
//
//        if ($request['unit_price'] <= $dis) {
//            $validator->after(function ($validator) {
//                $validator->errors()->add(
//                    'unit_price', 'Discount can not be more or equal to the price!'
//                );
//            });
//        }
//
//        // if (is_null($request->description[array_search('en', $request->lang)])) {
//        //     $validator->after(function ($validator) {
//        //         $validator->errors()->add(
//        //             'description', 'description field is required!'
//        //         );
//        //     });
//        // }
//
//        if (is_null($request->name[array_search('en', $request->lang)])) {
//            $validator->after(function ($validator) {
//                $validator->errors()->add(
//                    'name', 'Name field is required!'
//                );
//            });
//        }
//
//        if (is_null($request->desc[array_search('en', $request->lang)])) {
//            $validator->after(function ($validator) {
//                $validator->errors()->add(
//                    'desc', 'desc field is required!'
//                );
//            });
//        }
//
//
//        $p = new Product();
//        $p->user_id = auth('admin')->id();
//        $p->added_by = "admin";
//        $p->name = $request->name[array_search('en', $request->lang)];
//        $p->desc = $request->desc[array_search('en', $request->lang)];
//        $p->code = $request->code;
//        $p->slug = Str::slug($request->name[array_search('en', $request->lang)], '-') . '-' . Str::random(6);
//
//        $category = [];
//
//        if ($request->category_id != null) {
//            $category[] = [
//                'id' => $request->category_id,
//                'position' => 1,
//            ];
//        }
//        if ($request->sub_category_id != null) {
//            $category[] = [
//                'id' => $request->sub_category_id,
//                'position' => 2,
//            ];
//        }
//        if ($request->sub_sub_category_id != null) {
//            $category[] = [
//                'id' => $request->sub_sub_category_id,
//                'position' => 3,
//            ];
//        }
//
//        $p->category_ids = json_encode($category);
//        $p->brand_id = $request->brand_id;
//        $p->unit = $request->unit;
//        $p->details = $request->description[array_search('en', $request->lang)];
//
//        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
//            $p->colors = json_encode($request->colors);
//        } else {
//            $colors = [];
//            $p->colors = json_encode($colors);
//        }
//        $choice_options = [];
//        if ($request->has('choice')) {
//            foreach ($request->choice_no as $key => $no) {
//                $str = 'choice_options_' . $no;
//                $item['name'] = 'choice_' . $no;
//                $item['title'] = $request->choice[$key];
//                $item['options']=$request[$str];
//                array_push($choice_options, $item);
//            }
//        }
//        $p->choice_options = json_encode($choice_options);
//        //combinations start
//        $options = [];
//        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
//            $colors_active = 1;
//            array_push($options, $request->colors);
//        }
////        if ($request->has('choice_no')) {
////            foreach ($request->choice_no as $key => $no) {
////                $name = 'choice_options_' . $no;
////                $my_str = implode('|', $request[$name]);
////                array_push($options, explode(',', $my_str));
////            }
////        }
//        //Generates the combinations of customer choice options
//
//        $combinations = Helpers::combinations($options);
//
//        $variations = [];
//        $stock_count = 0;
//        if (count($combinations[0]) > 0) {
//            foreach ($combinations as $key => $combination) {
//                $str = '';
//                foreach ($combination as $k => $item) {
//                    if ($k > 0) {
//                        $str .= '-' . str_replace(' ', '', $item);
//                    } else {
//                        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
//                            $color_name = Color::where('code', $item)->first()->name;
//                            $str .= $color_name;
//                        } else {
//                            $str .= str_replace(' ', '', $item);
//                        }
//                    }
//                }
//                $item = [];
//                $item['type'] = $str;
//                $item['price'] = BackEndHelper::currency_to_usd(abs($request['price_' . str_replace('.', '_', $str)]));
//                $item['sku'] = $request['sku_' . str_replace('.', '_', $str)];
//                $item['file'] = ImageManager::upload('product/', 'webp', $request['file_' . str_replace('.', '_', $str)]);
//                $item['qty'] = abs($request['qty_' . str_replace('.', '_', $str)]);
//                array_push($variations, $item);
//                $stock_count += $item['qty'];
//            }
//        } else {
//            $stock_count = (integer)$request['current_stock'];
//        }
//
//        if ($validator->errors()->count() > 0) {
//            return response()->json(['errors' => Helpers::error_processor($validator)]);
//        }
//
//        //combinations end
//        $p->variation = json_encode($variations);
//        $p->unit_price = BackEndHelper::currency_to_usd($request->unit_price);
//        $p->purchase_price = BackEndHelper::currency_to_usd($request->purchase_price);
//        $p->tax = $request->tax_type == 'flat' ? BackEndHelper::currency_to_usd($request->tax) : $request->tax;
//        $p->tax_type = $request->tax_type;
//        $p->discount = $request->discount_type == 'flat' ? BackEndHelper::currency_to_usd($request->discount) : $request->discount;
//        $p->discount_type = $request->discount_type;
//        $p->attributes = json_encode($request->choice_attributes);
//        $p->current_stock = abs($stock_count);
//        $p->minimum_order_qty = $request->minimum_order_qty;
//
//        $p->video_provider = 'youtube';
//        $p->video_url = $request->video_link;
//        $p->request_status = 1;
//        $p->shipping_cost = BackEndHelper::currency_to_usd($request->shipping_cost);
//        $p->multiply_qty = $request->multiplyQTY=='on'?1:0;
//
//        if ($request->ajax()) {
//            return response()->json([], 200);
//        } else {
//            if ($request->file('images')) {
//                foreach ($request->file('images') as $img) {
//                    $product_images[] = ImageManager::upload('product/', 'webp', $img);
//                }
//                $p->images = json_encode($product_images);
//            }
//            $p->thumbnail = ImageManager::upload('product/thumbnail/', 'webp', $request->image);
//
//            $p->meta_title = $request->meta_title;
//            $p->meta_description = $request->meta_description;
//            $p->meta_image = ImageManager::upload('product/meta/', 'webp', $request->meta_image);
//
//            $p->save();
//
//            $data = [];
//            foreach ($request->lang as $index => $key) {
//                if ($request->name[$index] && $key != 'en') {
//                    $data[] = array(
//                        'translationable_type' => 'App\Model\Product',
//                        'translationable_id' => $p->id,
//                        'locale' => $key,
//                        'key' => 'name',
//                        'value' => $request->name[$index],
//                    );
//                }
//                if ($request->description[$index] && $key != 'en') {
//                    $data[] = array(
//                        'translationable_type' => 'App\Model\Product',
//                        'translationable_id' => $p->id,
//                        'locale' => $key,
//                        'key' => 'description',
//                        'value' => $request->description[$index],
//                    );
//                }
//            }
//            Translation::insert($data);
//
//            Toastr::success(translate('Product added successfully!'));
//            return redirect()->route('admin.product.list', ['in_house']);
//        }
    }

    function list(Request $request, $type)
    {
        $query_param = [];
        $search = $request['search'];
        if ($type == 'in_house') {
            $pro = Product::where(['added_by' => 'admin']);
        } else {
            $pro = Product::where(['added_by' => 'seller'])->where('request_status', $request->status);
        }

        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $pro = $pro->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->Where('name', 'like', "%{$value}%");
                }
            });
            $query_param = ['search' => $request['search']];
        }

        $request_status = $request['status'];
        $pro = $pro->orderBy('id', 'DESC')->paginate(Helpers::pagination_limit())->appends(['status' => $request['status']])->appends($query_param);
        return view('admin-views.product.list', compact('pro', 'search', 'request_status'));
    }

    public function updated_product_list(Request $request)
    {
        $query_param = [];
        $search = $request['search'];
        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $pro = Product::where(['added_by' => 'seller'])
                ->where('is_shipping_cost_updated',0)
                ->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->Where('name', 'like', "%{$value}%");
                    }
                });
            $query_param = ['search' => $request['search']];
        } else {
            $pro = Product::where(['added_by' => 'seller'])->where('is_shipping_cost_updated',0);
        }
        $pro = $pro->orderBy('id', 'DESC')->paginate(Helpers::pagination_limit())->appends($query_param);

        return view('admin-views.product.updated-product-list', compact('pro', 'search'));
    }

    public function stock_limit_list(Request $request, $type)
    {
        $stock_limit = Helpers::get_business_settings('stock_limit');
        $sort_oqrderQty = $request['sort_oqrderQty'];
        $query_param = $request->all();
        $search = $request['search'];
        if ($type == 'in_house') {
            $pro = Product::where(['added_by' => 'admin']);
        } else {
            $pro = Product::where(['added_by' => 'seller'])->where('request_status', $request->status);
        }

        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $pro = $pro->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->Where('name', 'like', "%{$value}%");
                }
            });
            $query_param = ['search' => $request['search']];
        }

        $request_status = $request['status'];

        $pro = $pro->withCount('order_details')->when($request->sort_oqrderQty == 'quantity_asc', function ($q) use ($request) {
            return $q->orderBy('current_stock', 'asc');
        })
            ->when($request->sort_oqrderQty == 'quantity_desc', function ($q) use ($request) {
                return $q->orderBy('current_stock', 'desc');
            })
            ->when($request->sort_oqrderQty == 'order_asc', function ($q) use ($request) {
                return $q->orderBy('order_details_count', 'asc');
            })
            ->when($request->sort_oqrderQty == 'order_desc', function ($q) use ($request) {
                return $q->orderBy('order_details_count', 'desc');
            })
            ->when($request->sort_oqrderQty == 'default', function ($q) use ($request) {
                return $q->orderBy('id');
            })->where('current_stock', '<', $stock_limit);

        $pro = $pro->orderBy('id', 'DESC')->paginate(Helpers::pagination_limit())->appends(['status' => $request['status']])->appends($query_param);
        return view('admin-views.product.stock-limit-list', compact('pro', 'search', 'request_status', 'sort_oqrderQty'));
    }

    public function update_quantity(Request $request)
    {
        $variations = [];
        $stock_count = $request['current_stock'];
        if ($request->has('type')) {
            foreach ($request['type'] as $key => $str) {
                $item = [];
                $item['type'] = $str;
                $item['price'] = BackEndHelper::currency_to_usd(abs($request['price_' . str_replace('.', '_', $str)]));
                $item['sku'] = $request['sku_' . str_replace('.', '_', $str)];
                $item['qty'] = abs($request['qty_' . str_replace('.', '_', $str)]);
                array_push($variations, $item);
            }
        }

        $product = Product::find($request['product_id']);
        if ($stock_count >= 0) {
            $product->current_stock = $stock_count;
            $product->variation = json_encode($variations);
            $product->save();
            Toastr::success(\App\CPU\translate('product_quantity_updated_successfully!'));
            return back();
        } else {
            Toastr::warning(\App\CPU\translate('product_quantity_can_not_be_less_than_0_!'));
            return back();
        }
    }

    public function status_update(Request $request)
    {

        $product = Product::where(['id' => $request['id']])->first();
        $success = 1;

        if ($request['status'] == 1) {
            if ($product->added_by == 'seller' && ($product->request_status == 0 || $product->request_status == 2)) {
                $success = 0;
            } else {
                $product->status = $request['status'];
            }
        } else {
            $product->status = $request['status'];
        }
        $product->save();
        return response()->json([
            'success' => $success,
        ], 200);
    }
    public function updated_shipping(Request $request)
    {

        $product = Product::where(['id' => $request['product_id']])->first();
        if($request->status == 1)
        {
            $product->shipping_cost = $product->temp_shipping_cost;
            $product->is_shipping_cost_updated = $request->status;
        }else{
            $product->is_shipping_cost_updated = $request->status;
        }

        $product->save();
        return response()->json([

        ], 200);
    }

    public function get_categories(Request $request)
    {
        $cat = Category::where(['parent_id' => $request->parent_id])->get();
        $res = '<option value="' . 0 . '" disabled selected>---Select---</option>';
        foreach ($cat as $row) {
            if ($row->id == $request->sub_category) {
                $res .= '<option value="' . $row->id . '" selected >' . $row->name . '</option>';
            } else {
                $res .= '<option value="' . $row->id . '">' . $row->name . '</option>';
            }
        }
        return response()->json([
            'select_tag' => $res,
        ]);
    }

    public function sku_combination(Request $request)
    {
        $options = [];
        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
            $colors_active = 1;
            array_push($options, $request->colors);
        } else {
            $colors_active = 0;
        }

        $unit_price = $request->unit_price;
        $product_name = $request->name[array_search('en', $request->lang)];

//        if ($request->has('choice_no')) {
//            foreach ($request->choice_no as $key => $no) {
//                $name = 'choice_options_' . $no;
//                $my_str = implode(',', $request[$name]);
//                array_push($options, explode(',', $my_str));
//            }
//        }

        $combinations = Helpers::combinations($options);
        return response()->json([
            'view' => view('admin-views.product.partials._sku_combinations', compact('combinations', 'unit_price', 'colors_active', 'product_name'))->render(),
        ]);
    }

    public function color_combination(Request $request)
    {
        $options = [];
        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
            $colors_active = 1;
            array_push($options, $request->colors);
        } else {
            $colors_active = 0;
        }

        $product_name = $request->name[array_search('en', $request->lang)];

        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_' . $no;
                $my_str = implode('', $request[$name]);
                array_push($options, explode(',', $my_str));
            }
        }

        $combinations = Helpers::combinations($options);
        return response()->json([
            'view' => view('admin-views.product.partials._color_combinations', compact('combinations', 'colors_active', 'product_name'))->render(),
        ]);
    }

    public function get_variations(Request $request)
    {
        $product = Product::find($request['id']);
        return response()->json([
            'view' => view('admin-views.product.partials._update_stock', compact('product'))->render()
        ]);
    }

    public function edit($id)
    {
        $product = Product::withoutGlobalScopes()->with('translations')->find($id);
        $product_category = json_decode($product->category_ids);
        $product->colors = json_decode($product->colors);
        $categories = Category::where(['parent_id' => 0])->get();
        $br = Brand::orderBY('name', 'ASC')->get();

        return view('admin-views.product.edit', compact('categories', 'br', 'product', 'product_category'));
    }

    public function update(Request $request, $id)
    {

        $product = Product::find($id);
        $validator = Validator::make($request->all(), [
            'name'              => 'required',
            'category_id'       => 'required',
            'brand_id'          => 'required',
            'unit'              => 'required',
            'tax'               => 'required|min:0',
            'unit_price'        => 'required|numeric|min:1',
            'purchase_price'    => 'required|numeric|min:1',
            'discount'          =>'required|gt:-1',
            'shipping_cost'     => 'required|gt:-1',
            'code'              => 'required|numeric|min:1|digits_between:6,20|unique:products,code,'.$product->id,
            'minimum_order_qty' => 'required|numeric|min:1',
        ], [
            'name.required'         => 'Product name is required!',
            'category_id.required'  => 'category  is required!',
            'brand_id.required'     => 'brand  is required!',
            'unit.required'         => 'Unit  is required!',
            'code.min'              => 'The code must be positive!',
            'code.digits_between'   => 'The code must be minimum 6 digits!',
            'minimum_order_qty.required' => 'The minimum order quantity is required!',
            'minimum_order_qty.min' => 'The minimum order quantity must be positive!',
        ]);

        if ($request['discount_type'] == 'percent') {
            $dis = ($request['unit_price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($request['unit_price'] <= $dis) {
            $validator->after(function ($validator) {
                $validator->errors()->add('unit_price', 'Discount can not be more or equal to the price!');
            });
        }

        if (is_null($request->name[array_search('en', $request->lang)])) {
            $validator->after(function ($validator) {
                $validator->errors()->add(
                    'name', 'Name field is required!'
                );
            });
        }

        if (is_null($request->desc[array_search('en', $request->lang)])) {
            $validator->after(function ($validator) {
                $validator->errors()->add(
                    'desc', 'desc field is required!'
                );
            });
        }

//         if (is_null($request->description[array_search('en', $request->lang)])) {
//             $validator->after(function ($validator) {
//                 $validator->errors()->add(
//                     'description', 'Description field is required!'
//                 );
//             });
//         }


        $product->name = $request->name[array_search('en', $request->lang)];
        $product->desc = $request->desc[array_search('en', $request->lang)];


        $category = [];
        if ($request->category_id != null) {
            $category[] = [
                'id' => $request->category_id,
                'position' => 1,
            ];
        }
        if ($request->sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_category_id,
                'position' => 2,
            ];
        }
        if ($request->sub_sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_sub_category_id,
                'position' => 3,
            ];
        }
        $product->category_ids = json_encode($category);
        $product->brand_id = $request->brand_id;
        $product->unit = $request->unit;
        $product->code = $request->code;
        $product->minimum_order_qty = $request->minimum_order_qty;
        $product->details = $request->description[array_search('en', $request->lang)];
        $product_images = json_decode($product->images);

        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
            $product->colors = json_encode($request->colors);
        } else {
            $colors = [];
            $product->colors = json_encode($colors);
        }
        $choice_options = [];
        if ($request->has('choice')) {
            foreach ($request->choice_no as $key => $no) {
                $str = 'choice_options_' . $no;
                $item['name'] = 'choice_' . $no;
                $item['title'] = $request->choice[$key];
                $item['options'] = $request[$str];
                array_push($choice_options, $item);
            }
        }
        $product->choice_options = json_encode($choice_options);
        $variations = [];
        //combinations start
        $options = [];
        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
            $colors_active = 1;
            array_push($options, $request->colors);
        }
//        if ($request->has('choice_no')) {
//            foreach ($request->choice_no as $key => $no) {
//                $name = 'choice_options_' . $no;
//                $my_str = implode('|', $request[$name]);
//                array_push($options, explode(',', $my_str));
//            }
//        }
        //Generates the combinations of customer choice options
        $combinations = Helpers::combinations($options);
        $variations = [];
        $stock_count = 0;
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $item) {
                    if ($k > 0) {
                        $str .= '-' . str_replace(' ', '', $item);
                    } else {
                        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
                            $color_name = Color::where('code', $item)->first()->name;
                            $str .= $color_name;
                        } else {
                            $str .= str_replace(' ', '', $item);
                        }
                    }
                }
                $item = [];
                $item['type'] = $str;
                $item['price'] = BackEndHelper::currency_to_usd(abs($request['price_' . str_replace('.', '_', $str)]));
                $item['sku'] = $request['sku_' . str_replace('.', '_', $str)];
                $item['file'] = ImageManager::upload('product/', 'webp', $request['file_' . str_replace('.', '_', $str)]);
                $item['qty'] = abs($request['qty_' . str_replace('.', '_', $str)]);
                array_push($variations, $item);
                $stock_count += $item['qty'];
            }
        } else {
            $stock_count = (integer)$request['current_stock'];
        }

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        // if ($validator->fails()) {
        //     return back()->withErrors($validator)
        //         ->withInput();
        // }

        //combinations end
        $product->variation = json_encode($variations);
        $product->unit_price = BackEndHelper::currency_to_usd($request->unit_price);
        $product->purchase_price = BackEndHelper::currency_to_usd($request->purchase_price);
        $product->tax = $request->tax == 'flat' ? BackEndHelper::currency_to_usd($request->tax) : $request->tax;
        $product->tax_type = $request->tax_type;
        $product->discount = $request->discount_type == 'flat' ? BackEndHelper::currency_to_usd($request->discount) : $request->discount;
        $product->attributes = json_encode($request->choice_attributes);
        $product->discount_type = $request->discount_type;
        $product->current_stock = abs($stock_count);

        $product->video_provider = 'youtube';
        $product->video_url = $request->video_link;
        if ($product->added_by == 'seller' && $product->request_status == 2) {
            $product->request_status = 1;
        }
        $product->shipping_cost = BackEndHelper::currency_to_usd($request->shipping_cost);
        $product->multiply_qty = $request->multiplyQTY=='on'?1:0;
        if ($request->ajax()) {
            return response()->json([], 200);
        } else {
            if ($request->file('images')) {
                foreach ($request->file('images') as $img) {
                    $product_images[] = ImageManager::upload('product/', 'webp', $img);
                }
                $product->images = json_encode($product_images);
            }

            if ($request->file('image')) {
                $product->thumbnail = ImageManager::update('product/thumbnail/', $product->thumbnail, 'webp', $request->file('image'));
            }

            $product->meta_title = $request->meta_title;
            $product->meta_description = $request->meta_description;
            if ($request->file('meta_image')) {
                $product->meta_image = ImageManager::update('product/meta/', $product->meta_image, 'webp', $request->file('meta_image'));
            }

            $product->save();

            foreach ($request->lang as $index => $key) {
                if ($request->name[$index] && $key != 'en') {
                    Translation::updateOrInsert(
                        ['translationable_type' => 'App\Model\Product',
                            'translationable_id' => $product->id,
                            'locale' => $key,
                            'key' => 'name'],
                        ['value' => $request->name[$index]]
                    );
                }
                if ($request->name[$index] && $key != 'en') {
                    Translation::updateOrInsert(
                        ['translationable_type' => 'App\Model\Product',
                            'translationable_id' => $product->id,
                            'locale' => $key,
                            'key' => 'desc'],
                        ['value' => $request->desc[$index]]
                    );
                }
                if ($request->description[$index] && $key != 'en') {
                    Translation::updateOrInsert(
                        ['translationable_type' => 'App\Model\Product',
                            'translationable_id' => $product->id,
                            'locale' => $key,
                            'key' => 'description'],
                        ['value' => $request->description[$index]]
                    );
                }
            }
            Toastr::success('Product updated successfully.');
            return back();
        }
    }

    public function remove_image(Request $request)
    {
        ImageManager::delete('/product/' . $request['image']);
        $product = Product::find($request['id']);
        $array = [];
        if (count(json_decode($product['images'])) < 2) {
            Toastr::warning('You cannot delete all images!');
            return back();
        }
        foreach (json_decode($product['images']) as $image) {
            if ($image != $request['name']) {
                array_push($array, $image);
            }
        }
        Product::where('id', $request['id'])->update([
            'images' => json_encode($array),
        ]);
        Toastr::success('Product image removed successfully!');
        return back();
    }

    public function delete($id)
    {
        $product = Product::find($id);

        $translation = Translation::where('translationable_type', 'App\Model\Product')
            ->where('translationable_id', $id);
        $translation->delete();

        Cart::where('product_id', $product->id)->delete();
        Wishlist::where('product_id', $product->id)->delete();

        foreach (json_decode($product['images'], true) as $image) {
            ImageManager::delete('/product/' . $image);
        }
        ImageManager::delete('/product/thumbnail/' . $product['thumbnail']);
        $product->delete();

        FlashDealProduct::where(['product_id' => $id])->delete();
        DealOfTheDay::where(['product_id' => $id])->delete();

        Toastr::success('Product removed successfully!');
        return back();
    }

    public function bulk_import_index()
    {
        return view('admin-views.product.bulk-import');
    }

    public function bulk_import_data(Request $request)
    {
        try {
            $collections = (new FastExcel)->import($request->file('products_file'));
        } catch (\Exception $exception) {
            Toastr::error('You have uploaded a wrong format file, please upload the right file.');
            return back();
        }


        $data = [];
        $skip = ['youtube_video_url', 'details', 'thumbnail'];
        foreach ($collections as $collection) {
            foreach ($collection as $key => $value) {
                if ($key!="" && $value === "" && !in_array($key, $skip)) {
                    Toastr::error('Please fill ' . $key . ' fields');
                    return back();
                }
            }

            $thumbnail = explode('/', $collection['thumbnail']);

            $data[] = [
                'name' => $collection['name'],
                'slug' => Str::slug($collection['name'], '-') . '-' . Str::random(6),
                'category_ids' => json_encode([['id' => (string)$collection['category_id'], 'position' => 1], ['id' => (string)$collection['sub_category_id'], 'position' => 2], ['id' => (string)$collection['sub_sub_category_id'], 'position' => 3]]),
                'brand_id' => $collection['brand_id'],
                'unit' => $collection['unit'],
                'min_qty' => $collection['min_qty'],
                'refundable' => $collection['refundable'],
                'unit_price' => $collection['unit_price'],
                'purchase_price' => $collection['purchase_price'],
                'tax' => $collection['tax'],
                'discount' => $collection['discount'],
                'discount_type' => $collection['discount_type'],
                'current_stock' => $collection['current_stock'],
                'details' => $collection['details'],
                'video_provider' => 'youtube',
                'video_url' => $collection['youtube_video_url'],
                'images' => json_encode(['def.png']),
                'thumbnail' => $thumbnail[1] ?? $thumbnail[0],
                'status' => 1,
                'request_status' => 1,
                'colors' => json_encode([]),
                'attributes' => json_encode([]),
                'choice_options' => json_encode([]),
                'variation' => json_encode([]),
                'featured_status' => 1,
                'added_by' => 'admin',
                'user_id' => auth('admin')->id(),
            ];
        }
        DB::table('products')->insert($data);
        Toastr::success(count($data) . ' - Products imported successfully!');
        return back();
    }

    public function bulk_export_data()
    {
        $products = Product::where(['added_by' => 'admin'])->get();
        //export from product
        $storage = [];
        foreach ($products as $item) {
            $category_id = 0;
            $sub_category_id = 0;
            $sub_sub_category_id = 0;
            foreach (json_decode($item->category_ids, true) as $category) {
                if ($category['position'] == 1) {
                    $category_id = $category['id'];
                } else if ($category['position'] == 2) {
                    $sub_category_id = $category['id'];
                } else if ($category['position'] == 3) {
                    $sub_sub_category_id = $category['id'];
                }
            }
            $storage[] = [
                'name' => $item->name,
                'category_id' => $category_id,
                'sub_category_id' => $sub_category_id,
                'sub_sub_category_id' => $sub_sub_category_id,
                'brand_id' => $item->brand_id,
                'unit' => $item->unit,
                'min_qty' => $item->min_qty,
                'refundable' => $item->refundable,
                'youtube_video_url' => $item->video_url,
                'unit_price' => $item->unit_price,
                'purchase_price' => $item->purchase_price,
                'tax' => $item->tax,
                'discount' => $item->discount,
                'discount_type' => $item->discount_type,
                'current_stock' => $item->current_stock,
                'details' => $item->details,
                'thumbnail' => 'thumbnail/' . $item->thumbnail,
            ];
        }
        return (new FastExcel($storage))->download('inhouse_products.xlsx');
    }

    public function barcode(Request $request, $id)
    {

        if ($request->limit > 270) {
            Toastr::warning(translate('You can not generate more than 270 barcode'));
             return back();
        }
        $product = Product::findOrFail($id);
        $limit =  $request->limit ?? 4;
        return view('admin-views.product.barcode', compact('product', 'limit'));
    }

}

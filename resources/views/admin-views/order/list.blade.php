@extends('layouts.back-end.app')

@section('title', \App\CPU\translate('Order List'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header mb-1">
            <div class="flex-between align-items-center">
                <div>
                    <h1 class="page-header-title">{{\App\CPU\translate('Orders')}} <span
                            class="badge badge-soft-dark mx-2">{{$orders->total()}}</span></h1>
                </div>
                <div>
                    <i class="tio-shopping-cart" style="font-size: 30px"></i>
                </div>
            </div>
            <!-- End Row -->

            <!-- Nav Scroller -->
            <div class="js-nav-scroller hs-nav-scroller-horizontal">
            <span class="hs-nav-scroller-arrow-prev" style="display: none;">
              <a class="hs-nav-scroller-arrow-link" href="javascript:;">
                <i class="tio-chevron-left"></i>
              </a>
            </span>

                <span class="hs-nav-scroller-arrow-next" style="display: none;">
              <a class="hs-nav-scroller-arrow-link" href="javascript:;">
                <i class="tio-chevron-right"></i>
              </a>
            </span>

                <!-- Nav -->
                <ul class="nav nav-tabs page-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">{{\App\CPU\translate('order_list')}}</a>
                    </li>
                </ul>
                <!-- End Nav -->
            </div>
            <!-- End Nav Scroller -->
        </div>
        <!-- End Page Header -->

        <!-- Card -->
        <div class="card">
            <!-- Header -->
            <div class="card-header">
                <div class="row flex-between justify-content-between flex-grow-1">
                    <div class="col-12 col-md-4">
                        <form action="{{ url()->current() }}" method="GET">
                            <!-- Search -->
                            <div class="input-group input-group-merge input-group-flush">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="tio-search"></i>
                                    </div>
                                </div>
                                <input id="datatableSearch_" type="search" name="search" class="form-control"
                                       placeholder="{{\App\CPU\translate('Search orders')}}" aria-label="Search orders" value="{{ $search }}"
                                       required>
                                <button type="submit" class="btn btn-primary">{{\App\CPU\translate('search')}}</button>
                            </div>
                            <!-- End Search -->
                        </form>
                    </div>
                    <div class="col-12 col-md-5 mt-2 mt-sm-0">
                        <form action="{{ url()->current() }}" method="GET">
                            
                            <div class="row">
                                
                                <div class="col-12 col-sm-5">
                                    <input type="date" name="from" value="{{$from}}" id="from_date"
                                            class="form-control" required>
                                </div>
                                <div class="col-12 col-sm-5 mt-2 mt-sm-0">
                                    <input type="date" value="{{$to}}" name="to" id="to_date"
                                            class="form-control" required> 
                                </div>
                                <div class="col-12 col-sm-2 mt-2 mt-sm-0  ">
                                    <button type="submit" class="btn btn-primary float-right float-sm-none">{{\App\CPU\translate('filter')}}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-12 col-md-3 mt-2 mt-md-0">
                        <div class="float-right">
                            <label> {{\App\CPU\translate('inhouse_orders_only')}} : </label>
                            <label class="switch ml-3">
                                <input type="checkbox" class="status"
                                       onclick="filter_order()" {{session()->has('show_inhouse_orders') && session('show_inhouse_orders')==1?'checked':''}}>
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
                </div>
                <!-- End Row -->
            </div>
            <!-- End Header -->

            <!-- Table -->
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                       style="width: 100%; text-align: {{Session::get('direction') === "rtl" ? 'right' : 'left'}}">
                    <thead class="thead-light">
                    <tr>
                        <th class="">
                            {{\App\CPU\translate('SL')}}#
                        </th>
                        <th>{{\App\CPU\translate('Order')}}</th>
                        <th>{{\App\CPU\translate('Date')}}</th>
                        <th>{{\App\CPU\translate('customer_name')}}</th>
                        <th>{{\App\CPU\translate('Status')}}</th>
                        <th>{{\App\CPU\translate('Total')}}</th>
                        <th>{{\App\CPU\translate('Order')}} {{\App\CPU\translate('Status')}} </th>
                        <th>{{\App\CPU\translate('Action')}}</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($orders as $key=>$order)

                        <tr class="status-{{$order['order_status']}} class-all">
                            <td class="">
                                {{$orders->firstItem()+$key}}
                            </td>
                            <td >
                                <a href="{{route('admin.orders.details',['id'=>$order['id']])}}">{{$order['id']}}</a>
                            </td>
                            <td>{{date('d M Y',strtotime($order['created_at']))}}</td>
                            <td>
                                @if($order->customer)
                                    <a class="text-body text-capitalize"
                                       href="{{route('admin.orders.details',['id'=>$order['id']])}}">{{$order->customer['f_name'].' '.$order->customer['l_name']}}</a>
                                @else
                                    <label class="badge badge-danger">{{\App\CPU\translate('invalid_customer_data')}}</label>
                                @endif
                            </td>
                            <td>
                                @if($order->payment_status=='paid')
                                    <span class="badge badge-soft-success">
                                      <span class="legend-indicator bg-success"
                                            style="{{Session::get('direction') === "rtl" ? 'margin-right: 0;margin-left: .4375rem;' : 'margin-left: 0;margin-right: .4375rem;'}}"></span>{{\App\CPU\translate('paid')}}
                                    </span>
                                @else
                                    <span class="badge badge-soft-danger">
                                      <span class="legend-indicator bg-danger"
                                            style="{{Session::get('direction') === "rtl" ? 'margin-right: 0;margin-left: .4375rem;' : 'margin-left: 0;margin-right: .4375rem;'}}"></span>{{\App\CPU\translate('unpaid')}}
                                    </span>
                                @endif
                            </td>
                            <td> {{\App\CPU\BackEndHelper::set_symbol(\App\CPU\BackEndHelper::usd_to_currency($order->order_amount))}}</td>
                            <td class="text-capitalize">
                                @if($order['order_status']=='pending')
                                    <span class="badge badge-soft-info  ">
                                        <span class="legend-indicator bg-info"
                                              style="{{Session::get('direction') === "rtl" ? 'margin-right: 0;margin-left: .4375rem;' : 'margin-left: 0;margin-right: .4375rem;'}}"></span>{{$order['order_status']}}
                                      </span>

                                @elseif($order['order_status']=='processing' || $order['order_status']=='out_for_delivery')
                                    <span class="badge badge-soft-warning  ">
                                        <span class="legend-indicator bg-warning"
                                              style="{{Session::get('direction') === "rtl" ? 'margin-right: 0;margin-left: .4375rem;' : 'margin-left: 0;margin-right: .4375rem;'}}"></span>{{$order['order_status']}}
                                      </span>
                                @elseif($order['order_status']=='confirmed')
                                    <span class="badge badge-soft-success  ">
                                        <span class="legend-indicator bg-success"
                                              style="{{Session::get('direction') === "rtl" ? 'margin-right: 0;margin-left: .4375rem;' : 'margin-left: 0;margin-right: .4375rem;'}}"></span>{{$order['order_status']}}
                                      </span>
                                @elseif($order['order_status']=='failed')
                                    <span class="badge badge-danger  ">
                                        <span class="legend-indicator bg-warning"
                                              style="{{Session::get('direction') === "rtl" ? 'margin-right: 0;margin-left: .4375rem;' : 'margin-left: 0;margin-right: .4375rem;'}}"></span>{{$order['order_status']}}
                                      </span>
                                @elseif($order['order_status']=='delivered')
                                    <span class="badge badge-soft-success  ">
                                        <span class="legend-indicator bg-success"
                                              style="{{Session::get('direction') === "rtl" ? 'margin-right: 0;margin-left: .4375rem;' : 'margin-left: 0;margin-right: .4375rem;'}}"></span>{{$order['order_status']}}
                                      </span>
                                @else
                                    <span class="badge badge-soft-danger  ">
                                        <span class="legend-indicator bg-danger"
                                              style="{{Session::get('direction') === "rtl" ? 'margin-right: 0;margin-left: .4375rem;' : 'margin-left: 0;margin-right: .4375rem;'}}"></span>{{$order['order_status']}}
                                      </span>
                                @endif
                            </td>
                            <td>
                                
                                <a class="btn btn-primary btn-sm mr-1" title="{{\App\CPU\translate('view')}}"
                                    href="{{route('admin.orders.details',['id'=>$order['id']])}}"><i
                                        class="tio-visible"></i> </a>
                                <a class="btn btn-info btn-sm mr-1" target="_blank" title="{{\App\CPU\translate('invoice')}}"
                                    href="{{route('admin.orders.generate-invoice',[$order['id']])}}"><i
                                        class="tio-download"></i> </a>
                            
                            </td> 
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <!-- End Table -->

            <!-- Footer -->
            <div class="card-footer">
                <!-- Pagination -->
                <div class="row table-responsive">
                    <div class="">
                        <div class="">
                            <!-- Pagination -->
                            {!! $orders->links() !!}
                        </div>
                    </div>
                </div>
                <!-- End Pagination -->
            </div>
            <!-- End Footer -->
        </div>
        <!-- End Card -->
    </div>
@endsection

@push('script_2')
    <script>
        function filter_order() {
            $.get({
                url: '{{route('admin.orders.inhouse-order-filter')}}',
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    toastr.success('{{\App\CPU\translate('order_filter_success')}}');
                    location.reload();
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        };
    </script>
    <script>
        $('#from_date,#to_date').change(function () {
            let fr = $('#from_date').val();
            let to = $('#to_date').val();
            if (fr != '' && to != '') {
                if (fr > to) {
                    $('#from_date').val('');
                    $('#to_date').val('');
                    toastr.error('{{\App\CPU\translate('Invalid date range')}}!', Error, {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            }

        })
    </script>
@endpush

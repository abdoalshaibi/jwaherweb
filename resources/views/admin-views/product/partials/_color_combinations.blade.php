@if(count($combinations[0]) > 0)
    <table class="table table-bordered">
        <thead>
        <tr>
            <td class="text-center">
                <label for="" class="control-label">{{\App\CPU\translate('Variant')}}</label>
            </td>

            <td class="text-center">
                <label for="" class="control-label">{{\App\CPU\translate('Image')}}</label>
            </td>
        </tr>
        </thead>
        <tbody>
        @endif
        @foreach ($combinations as $key => $combination)
            @php
                $sku = '';
                foreach (explode(' ', $product_name) as $key => $value) {
                    $sku .= substr($value, 0, 1);
                }

                $str = '';
                foreach ($combination as $key => $item){
                    if($key > 0 ){
                        $str .= '-'.str_replace(' ', '', $item);
                        $sku .='-'.str_replace(' ', '', $item);
                    }
                    else{
                        if($colors_active == 1){
                            $color_name = \App\Model\Color::where('code', $item)->first()->name;
                            $str .= $color_name;
                            $sku .='-'.$color_name;
                        }
                        else{
                            $str .= str_replace(' ', '', $item);
                            $sku .='-'.str_replace(' ', '', $item);
                        }
                    }
                }
        //	@endphp

            @if(strlen($str) > 0)
                <tr>
                    <td>
                        <label for="" class="control-label">{{ $str }}</label>
                    </td>
                    <td>
                        <input type="file" id="{{$str}}" onchange="loadPreview(this)" name="image[]"  value="{{old($str)}}"  multiple/>
                        <span class="text-danger">{{ $errors->first('image') }}</span>
                        <div id="thumb-{{$str}}"></div>
                    </td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>

    <script>

        function loadPreview(input){
            var data = $(input)[0].files; //this file data
            $.each(data, function(index, file){
                if(/(\.|\/)(gif|jpe?g|png)$/i.test(file.type)){
                    var fRead = new FileReader();
                    fRead.onload = (function(file){
                        return function(e) {
                            var img = $('<img/>').addClass('thumb').attr('src', e.target.result); //create image thumb element
                            $('#thumb-'+$(input)[0].id).append(img);
                        };
                    })(file);
                    fRead.readAsDataURL(file);
                }
            });
        };

    </script>

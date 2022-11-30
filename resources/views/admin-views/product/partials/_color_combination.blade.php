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
{{--                    <div class="p-2 border border-dashed" style="max-width:430px;">--}}
{{--                        <div class="row" id="coba_{{$str}}" onfocus="loadPreview(this)"></div>--}}
{{--                    </div>--}}
				</td>
			</tr>
	@endif
@endforeach
	</tbody>
</table>

<script>
	update_qty();

	function update_qty()
	{
		var total_qty = 0;
		var qty_elements = $('input[name^="qty_"]');

		for(var i=0; i<qty_elements.length; i++)
		{
			total_qty += parseInt(qty_elements.eq(i).val());
		}
		if(qty_elements.length > 0)
		{
			$('input[name="current_stock"]').attr("readonly", true);
			$('input[name="current_stock"]').val(total_qty);
		}
		else{
			$('input[name="current_stock"]').attr("readonly", false);
		}
	}
	$('input[name^="qty_"]').on('keyup', function () {
		var total_qty = 0;
		var qty_elements = $('input[name^="qty_"]');
		for(var i=0; i<qty_elements.length; i++)
		{
            alert(qty_elements.eq(i).text());

            total_qty += parseInt(qty_elements.eq(i).val());
		}
		$('input[name="current_stock"]').val(total_qty);
	});

        function Update_Image(input) {
            alert($(input)[0].id);
        $("#"+$(input)[0].id).spartanMultiImagePicker({
            fieldName: 'images[]',
            maxCount: 10,
            rowHeight: 'auto',
            groupClassName: 'col-6',
            allowedExt:'',
            maxFileSize: '',
            placeholderImage: {
                image: '{{ asset('public/assets/back-end/img/400x400/img2.jpg') }}',
                width: '100%',
            },
            dropFileLabel: "Drop Here",
            onAddRow: function(index, file) {

            },
            onRenderedPreview: function(index) {

            },
            onRemoveRow: function(index) {

            },
            onExtensionErr: function(index, file) {
                toastr.error(
                    '{{ \App\CPU\translate('Please only input png or jpg type file') }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
            },
            onSizeErr: function(index, file) {
                toastr.error('{{ \App\CPU\translate('File size too big') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
            }
        })};

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

<div class="mb-3">
    <label>Customer *</label>
    <select name="customer_id" class="form-control" required>
        <option value="">  Select Customer </option>
        @foreach($customers as $customer)
            <option value="{{ $customer->id }}" {{ old('customer_id', $invoice->customer_id ?? '') == $customer->id ? 'selected' : '' }}>
                {{ $customer->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label>Customer Registration type *</label>
    <select name="registration_type" class="form-control" required>
        <option value="">Select Customer Registration type </option>
        <option value="Registered" {{ old('registration_type', $invoice->registration_type ?? '') == 'Registered' ? 'selected' : '' }}>Registered</option>
        <option value="Unregistered" {{ old('registration_type', $invoice->registration_type ?? '') == 'Unregistered' ? 'selected' : '' }}>Unregistered</option>
        </select>
</div>


<div class="mb-3">
    <label>Date of Supply *</label>
    <input type="date" name="date_of_supply" class="form-control" 
        value="{{ old('date_of_supply', $invoice->date_of_supply ?? now()->format('Y-m-d')) }}" required>
</div>        

<div class="mb-3">
    <label>Time of Supply *</label>
    <input type="time" name="time_of_supply" class="form-control" 
        value="{{ old('time_of_supply', $invoice->time_of_supply ?? now()->format('H:i')) }}" required>
</div>        
<style>
    .table {
    table-layout: auto;
    width: 100%;
    white-space: nowrap;
}

/* Specific column widths */
.col-hscode   { min-width: 120px; text-align: center; }
.col-name     { min-width: 200px; }
.col-desc     { min-width: 250px; }
.col-uom      { min-width: 80px; text-align: center; }
.col-num      { min-width: 110px; text-align: center; }

/* Make header sticky (optional for UX) */
thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 2;
}


</style>
<h5 class="mt-4 mb-2">Invoice Items</h5>
<div id="items-table-wrapper" style="overflow-x: auto; width: 100%;">

    <table class="table table-bordered table-striped table-sm align-middle" id="items-table" style="min-width: 1200px; width: auto;">
        <thead class="table-light text-center">
            <tr>
                
                <th class="col-hscode">H.S.Code</th>
                <th class="col-name">Product Name</th>
                <th class="col-desc">Description</th>
                <th class="col-uom">UOM</th>
                <th class="col-num">Unit Price</th>
                <th class="col-num">Qty</th>
                <th class="col-num">Amount</th>
                <th class="col-num">ST %</th>
                <th class="col-num">ST Amount</th>
                <th class="col-num">ST withheld as WH</th>
                <th class="col-num">Extra Tax</th>
                <th class="col-num">Further Tax</th>
                <th class="col-num">Total</th>
                <th>
                    <button type="button" class="btn btn-sm btn-success" id="add-row">
                        <i class="fas fa-plus"></i>
                    </button>
                </th>
            </tr>
        </thead>
        <tbody>
            @php
                $uom_list = [
                    "MT", "Bill of lading", "SET", "KWH", "40KG", "Liter", "SqY", 
                    "Bag", "KG", "MMBTU", "Meter", "Pcs", "Carat", "Cubic Metre", 
                    "Dozen", "Gram", "Gallon", "Kilogram", "Pound", "Timber Logs", 
                    "Numbers, pieces, units", "Packs", "Pair", "Square Foot", 
                    "Square Metre", "Thousand Unit", "Mega Watt", "Foot", "Barrels", 
                    "NO", "Others", "1000 kWh"
                ];
                
                // Duplicate descriptions remove karne ke liye (agar list mein repeat ho rahe hon)
                $uom_list = array_unique($uom_list);
            @endphp
            @if(old('items') || isset($invoice))
                @php
                    $itemRows = old('items', isset($invoice) ? $invoice->items->toArray() : []);
                @endphp
                @foreach($itemRows as $i => $row)
                <tr>
                    <td><input type="text" name="items[{{ $i }}][hs_code]" class="form-control" value="{{ $row['hs_code'] ?? '' }}"></td>
                    <td><input type="text" name="items[{{ $i }}][product_name]" class="form-control" value="{{ $row['product_name'] ?? ($row['item']['name'] ?? '') }}"></td>
                    <td><input type="text" name="items[{{ $i }}][description]" class="form-control" value="{{ $row['description'] ?? '' }}"></td>
                    <td>
                        <select name="items[{{ $i }}][uom]" class="form-control" required>
                            <option value="">Select UOM</option>
                            @foreach($uom_list as $uom)
                                <option value="{{ $uom }}" {{ (isset($row['uom']) && $row['uom'] == $uom) ? 'selected' : '' }}>
                                    {{ $uom }}
                                </option>
                            @endforeach
                        </select>
                    </td>

                    <td><input type="text" step="0.01" name="items[{{ $i }}][unit_price]" class="form-control numeric-only unit-price" value="{{ $row['unit_price'] ?? '' }}" required></td>
                    <td><input type="text" name="items[{{ $i }}][quantity]" class="form-control numeric-only quantity" value="{{ $row['quantity'] ?? 1 }}" min="1" required></td>
                    <td><input type="text" step="0.01" name="items[{{ $i }}][value_of_goods]" class="form-control numeric-only value" value="{{ $row['value_of_goods'] ?? '' }}" readonly></td>
                    <td><input type="text" step="0.01" name="items[{{ $i }}][sale_tax_rate]" class="form-control numeric-only st-rate" value="{{ $row['sale_tax_rate'] ?? 18 }}"></td>
                    <td><input type="text" step="0.01" name="items[{{ $i }}][amount_of_saleTax]" class="form-control numeric-only st-amount" value="{{ $row['amount_of_saleTax'] ?? '' }}" readonly></td>
                    <td><input type="text" step="0.01" name="items[{{ $i }}][sale_tax_withheld]" class="form-control numeric-only stw" value="{{ $row['sale_tax_withheld'] ?? 0 }}"></td>
                    <td><input type="text" step="0.01" name="items[{{ $i }}][extra_tax]" class="form-control numeric-only et" value="{{ $row['extra_tax'] ?? 0 }}"></td>
                    <td><input type="text" step="0.01" name="items[{{ $i }}][further_tax]" class="form-control numeric-only ft" value="{{ $row['further_tax'] ?? 0 }}"></td>
                    <td><input type="text" step="0.01" name="items[{{ $i }}][total]" class="form-control numeric-only total" value="{{ $row['total'] ?? '' }}" readonly></td>
                    <td><button type="button" class="btn btn-sm btn-danger remove-row"><i class="fas fa-trash"></i></button></td>
                </tr>
                @endforeach
            @else
            <tr>
                <td><input type="text" name="items[0][hs_code]" class="form-control"></td>
                <td><input type="text" name="items[0][product_name]" class="form-control"></td>
                <td><input type="text" name="items[0][description]" class="form-control"></td>
                <td>
                    <select name="items[0][uom]" class="form-control" required>
                        <option value="">Select UOM</option>
                        @foreach($uom_list as $uom)
                            <option value="{{ $uom }}" {{ (isset($row['uom']) && $row['uom'] == $uom) ? 'selected' : '' }}>
                                {{ $uom }}
                            </option>
                        @endforeach
                    </select>
                </td>

                <td><input type="text" step="0.01" name="items[0][unit_price]" class="form-control numeric-only unit-price" required></td>
                <td><input type="text" name="items[0][quantity]" class="form-control numeric-only quantity" value="1" min="1" required></td>
                <td><input type="text" step="0.01" name="items[0][value_of_goods]" class="form-control numeric-only value" readonly></td>
                <td><input type="text" step="0.01" name="items[0][sale_tax_rate]" class="form-control numeric-only st-rate" value="18"></td>
                <td><input type="text" step="0.01" name="items[0][amount_of_saleTax]" class="form-control numeric-only st-amount" readonly></td>
                <td><input type="text" step="0.01" name="items[0][sale_tax_withheld]" class="form-control numeric-only stw" value="0"></td>
                <td><input type="text" step="0.01" name="items[0][extra_tax]" class="form-control numeric-only et" value="0"></td>
                <td><input type="text" step="0.01" name="items[0][further_tax]" class="form-control numeric-only ft" value="0"></td>
                <td><input type="text" step="0.01" name="items[0][total]" class="form-control numeric-only total" readonly></td>
                <td><button type="button" class="btn btn-sm btn-danger remove-row"><i class="fas fa-trash"></i></button></td>
            </tr>
            @endif
        </tbody>
    </table>
</div>


@push('scripts')
<script>
let rowIndex = {{ isset($invoice) ? ($invoice->items->count() ?? 0) : 1 }};


function calculateRow(row) {
    const price = parseFloat(row.find('.unit-price').val()) || 0;
    const qty   = parseFloat(row.find('.quantity').val()) || 1;
    const rate  = parseFloat(row.find('.st-rate').val()) || 18;
    const et    = parseFloat(row.find('.et').val()) || 0;
    const stw   = parseFloat(row.find('.stw').val()) || 0;

    let ft      = parseFloat(row.find('.ft').val()) || 0;

    const value = price * qty;
    const tax   = value * rate / 100;

    // 🔥 Registration type check
    const regType = $('select[name="registration_type"]').val();
    if (regType === "Unregistered") {
        ft = round2(value * 0.04); // 4% of value_of_goods
        row.find('.ft').val(ft.toFixed(2));
    }

console.log('val ' + value);
console.log('tax ' + tax);
const value2 = round2(value);
const tax2   = round2(tax);
console.log('val2 ' + value2);
console.log('tax2 ' + tax2);
const et2    = round2(et);
const stw2   = round2(stw);
const ft2    = round2(ft);

const total = round2(
    value2 + tax2 + et2 + stw2 + ft2
);


    row.find('.value').val(value2.toFixed(2));
    row.find('.st-amount').val(tax2.toFixed(2));
    row.find('.total').val(total.toFixed(2));
}

function round2(num) {
    return Math.round((parseFloat(num) || 0) * 100) / 100;
}

$(document).on('input change', '.unit-price, .quantity, .st-rate, .et, .stw, .ft, select[name="registration_type"]', function () {
    $('#items-table tbody tr').each(function () {
        calculateRow($(this));
    });
});

$('#add-row').on('click', function () {
    const uomOptions = [
        "MT", "Bill of lading", "SET", "KWH", "40KG", "Liter", "SqY", 
        "Bag", "KG", "MMBTU", "Meter", "Pcs", "Carat", "Cubic Metre", 
        "Dozen", "Gram", "Gallon", "Kilogram", "Pound", "Timber Logs", 
        "Numbers, pieces, units", "Packs", "Pair", "Square Foot", 
        "Square Metre", "Thousand Unit", "Mega Watt", "Foot", "Barrels", 
        "NO", "Others", "1000 kWh"
    ];
    let uomHtml = '<option value="">Select UOM</option>';
    uomOptions.forEach(function(uom) {
        uomHtml += `<option value="${uom}">${uom}</option>`;
    });


    const newRow = `
        <tr>
            <td><input type="text" name="items[${rowIndex}][hs_code]" class="form-control"></td>
            <td><input type="text" name="items[${rowIndex}][product_name]" class="form-control"></td>
            <td><input type="text" name="items[${rowIndex}][description]" class="form-control"></td>
            <td>
                <select name="items[${rowIndex}][uom]" class="form-control">
                    ${uomHtml}
                </select>
            </td>
            <td><input type="text" step="0.01" name="items[${rowIndex}][unit_price]" class="form-control numeric-only unit-price" required></td>
            <td><input type="text" name="items[${rowIndex}][quantity]" class="form-control numeric-only quantity" value="1" min="1" required></td>
            <td><input type="text" step="0.01" name="items[${rowIndex}][value_of_goods]" class="form-control numeric-only value" readonly></td>
            <td><input type="text" step="0.01" name="items[${rowIndex}][sale_tax_rate]" class="form-control numeric-only st-rate" value="18"></td>
            <td><input type="text" step="0.01" name="items[${rowIndex}][amount_of_saleTax]" class="form-control numeric-only st-amount" readonly></td>
            <td><input type="text" step="0.01" name="items[${rowIndex}][sale_tax_withheld]" class="form-control numeric-only stw" value="0"></td>
            <td><input type="text" step="0.01" name="items[${rowIndex}][extra_tax]" class="form-control numeric-only et" value="0"></td>
            <td><input type="text" step="0.01" name="items[${rowIndex}][further_tax]" class="form-control numeric-only ft" value="0"></td>
            <td><input type="text" step="0.01" name="items[${rowIndex}][total]" class="form-control numeric-only total" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-row"><i class="fas fa-trash"></i></button></td>
        </tr>`;

    $('#items-table tbody').append(newRow);
    rowIndex++;
});

$(document).on('click', '.remove-row', function () {
    $(this).closest('tr').remove();
});

</script>
<script>
function allowOnlyNumbers(el) {
    el.value = el.value
        .replace(/[^0-9.]/g, '')     // allow only numbers & dot
        .replace(/(\..*)\./g, '$1'); // allow only one dot
}

// attach event to all numeric inputs
document.addEventListener('input', function (e) {
    if (e.target.classList.contains('numeric-only')) {
        allowOnlyNumbers(e.target);
    }
});
</script>
@endpush
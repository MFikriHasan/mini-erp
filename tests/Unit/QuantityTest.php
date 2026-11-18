<?php

use App\Support\Quantity;

// ---- Material bilangan bulat (PCS, qty_decimals = 0) ----
it('menerima bilangan bulat untuk material 0 desimal', function () {
    expect(Quantity::isValid('3', 0))->toBeTrue()
        ->and(Quantity::isValid('3.0', 0))->toBeTrue()   // nol di akhir diabaikan
        ->and(Quantity::isValid(10, 0))->toBeTrue();
});

it('menolak pecahan untuk material 0 desimal', function () {
    expect(Quantity::error('0.5', 0))->toBe('Kuantitas hanya boleh bilangan bulat.')
        ->and(Quantity::isValid('2.7', 0))->toBeFalse();
});

// ---- Material berat/volume (KG, qty_decimals = 3) ----
it('menerima hingga 3 desimal untuk material 3 desimal', function () {
    expect(Quantity::isValid('12.5', 3))->toBeTrue()
        ->and(Quantity::isValid('12.500', 3))->toBeTrue()
        ->and(Quantity::isValid('0.001', 3))->toBeTrue();
});

it('menolak lebih dari batas desimal material', function () {
    expect(Quantity::isValid('12.5555', 3))->toBeFalse()
        ->and(Quantity::isValid('2.555', 2))->toBeFalse()
        ->and(Quantity::error('2.555', 2))->toBe('Kuantitas maksimal 2 angka di belakang koma.');
});

// ---- Aturan umum ----
it('menolak nol, negatif, kosong, dan non-angka', function (mixed $input) {
    expect(Quantity::isValid($input, 3))->toBeFalse();
})->with(['0', '0.000', '-1', '-0.5', '', '  ', 'abc', '1e3', '1,5', '1.', '.5', '12.5kg']);

it('menolak angka terlalu besar untuk decimal(15,3)', function () {
    expect(Quantity::isValid('1000000000000', 3))->toBeFalse()
        ->and(Quantity::isValid('999999999999.999', 3))->toBeTrue();
});

// ---- Aritmetika: jebakan float ----
it('menghitung tanpa galat float', function () {
    // 0.1 + 0.2 != 0.3 pada float; harus tepat pada bcmath
    expect(Quantity::add('0.100', '0.200'))->toBe('0.300')
        ->and(Quantity::compare(Quantity::add('0.100', '0.200'), '0.300'))->toBe(0);
});

it('menghitung sisa PO secara tepat', function () {
    $remaining = Quantity::sub('12.500', '4.125');   // dipesan 12.5, diterima 4.125

    expect($remaining)->toBe('8.375')
        ->and(Quantity::compare('8.376', $remaining))->toBe(1)   // 8.376 melebihi sisa
        ->and(Quantity::compare('8.375', $remaining))->toBe(0);  // tepat menghabiskan
});

it('memformat tampilan sesuai desimal material', function () {
    expect(Quantity::format('12.5', 3))->toBe('12,500')
        ->and(Quantity::format('12', 0))->toBe('12')
        ->and(Quantity::format('1234.5', 2))->toBe('1.234,50');
});

// ---- Regresi: nilai float dari SQLite dan nilai null dari model baru ----
it('tidak memotong float ke int', function () {
    // SQLite mengembalikan decimal sebagai float; PHP bisa memotong 12.5 menjadi 12 bila tidak dinormalisasi
    expect(Quantity::compare(12.5, '12.500'))->toBe(0)
        ->and(Quantity::sub(12.5, 4.125))->toBe('8.375')
        ->and(Quantity::add(0.25, 0.5))->toBe('0.750');
});

it('memperlakukan null dan string kosong sebagai nol', function () {
    expect(Quantity::sub('12.500', null))->toBe('12.500')
        ->and(Quantity::add(null, '1.5'))->toBe('1.500')
        ->and(Quantity::compare('', '0.000'))->toBe(0);
});

it('memformat nilai float dan null', function () {
    expect(Quantity::format(12.5, 3))->toBe('12,500')
        ->and(Quantity::format(null, 2))->toBe('0,00');
});

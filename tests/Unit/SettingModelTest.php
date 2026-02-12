<?php

use App\Models\Setting;

/*
|--------------------------------------------------------------------------
| Setting Model — Get, Set, Encrypted, Cache
|--------------------------------------------------------------------------
*/

test('setting can be stored and retrieved', function () {
    Setting::set('test_key', 'test_value', 'testing');

    expect(Setting::get('test_key'))->toBe('test_value');
});

test('setting returns default when key does not exist', function () {
    expect(Setting::get('nonexistent_key', 'default'))->toBe('default');
});

test('setting can store boolean values', function () {
    Setting::set('bool_test', true, 'testing', 'boolean');

    expect(Setting::get('bool_test'))->toBeTrue();
});

test('setting can store integer values', function () {
    Setting::set('int_test', 42, 'testing', 'integer');

    expect(Setting::get('int_test'))->toBe(42);
});

test('setting can store array/json values', function () {
    Setting::set('array_test', ['a', 'b', 'c'], 'testing', 'json');

    $result = Setting::get('array_test');
    expect($result)->toBeArray();
    expect($result)->toContain('a');
});

test('setting can be updated', function () {
    Setting::set('update_test', 'original', 'testing');
    Setting::set('update_test', 'updated', 'testing');

    expect(Setting::get('update_test'))->toBe('updated');
});

test('encrypted setting can be stored and retrieved', function () {
    Setting::setEncrypted('secret_key', 'super_secret_value', 'testing');

    // Raw value in DB should be encrypted (not the plain text)
    $raw = Setting::where('key', 'secret_key')->first();
    expect($raw->value)->not->toBe('super_secret_value');
    expect($raw->type)->toBe('encrypted');

    // Decrypted retrieval should return plain text
    expect(Setting::getDecrypted('secret_key'))->toBe('super_secret_value');
});

test('getDecrypted returns default for missing key', function () {
    expect(Setting::getDecrypted('missing_encrypted', 'fallback'))->toBe('fallback');
});

test('getGroup returns all settings in a group', function () {
    Setting::set('group_a', 'value_a', 'mygroup');
    Setting::set('group_b', 'value_b', 'mygroup');
    Setting::set('other', 'value_c', 'othergroup');

    $group = Setting::getGroup('mygroup');
    expect($group)->toHaveKey('group_a');
    expect($group)->toHaveKey('group_b');
    expect($group)->not->toHaveKey('other');
});

test('getPublic returns only public settings', function () {
    Setting::create(['key' => 'pub_setting', 'value' => 'yes', 'group' => 'test', 'type' => 'string', 'is_public' => true]);
    Setting::create(['key' => 'priv_setting', 'value' => 'no', 'group' => 'test', 'type' => 'string', 'is_public' => false]);

    $public = Setting::getPublic();
    expect($public)->toHaveKey('pub_setting');
    expect($public)->not->toHaveKey('priv_setting');
});

test('clearCache does not throw', function () {
    Setting::set('cache_test', 'value', 'testing');
    Setting::clearCache();

    // Should still be retrievable from DB after cache clear
    expect(Setting::get('cache_test'))->toBe('value');
});

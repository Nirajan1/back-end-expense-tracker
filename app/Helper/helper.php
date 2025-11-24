<?php

use Illuminate\Support\Str;

function uploadImage($request, $object, $fileName)
{
    if ($request->hasFile($fileName)) {
        $file = $request->file($fileName);

        // Generate a unique file name: timestamp + random string + extension
        $newName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();

        // Store the file in 'public/images'
        $path = $file->storeAs('images', $newName, 'public');

        // Save the file path to the model
        $object->$fileName = 'storage/' . $path;
    }
}

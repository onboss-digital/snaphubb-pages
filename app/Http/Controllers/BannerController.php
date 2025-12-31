<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    /**
     * Fazer upload de banner
     */
    public function upload(Request $request)
    {
        $request->validate([
            'banner' => 'required|image|mimes:jpeg,png,gif,webp|max:5120', // 5MB max
            'language' => 'required|in:br,en,es',
            'device' => 'required|in:desktop,mobile',
        ]);

        try {
            $file = $request->file('banner');
            $filename = "{$request->language}-{$request->device}." . $file->getClientOriginalExtension();
            
            // Armazenar em storage/app/public/banners
            $path = Storage::disk('public')->putFileAs('banners', $file, $filename);

            return response()->json([
                'success' => true,
                'message' => 'Banner enviado com sucesso!',
                'path' => asset('storage/' . $path),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer upload: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Listar todos os banners
     */
    public function list()
    {
        $files = Storage::disk('public')->files('banners');
        
        $banners = [];
        foreach ($files as $file) {
            $banners[] = [
                'filename' => basename($file),
                'url' => asset('storage/' . $file),
                'size' => Storage::disk('public')->size($file),
            ];
        }

        return response()->json(['banners' => $banners]);
    }

    /**
     * Deletar banner
     */
    public function delete($filename)
    {
        try {
            Storage::disk('public')->delete('banners/' . $filename);
            
            return response()->json([
                'success' => true,
                'message' => 'Banner deletado com sucesso!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao deletar: ' . $e->getMessage(),
            ], 400);
        }
    }
}

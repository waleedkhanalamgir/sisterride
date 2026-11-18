import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

/// A tappable box that lets the user take or choose a photo and shows a preview.
class PhotoField extends StatelessWidget {
  const PhotoField({
    super.key,
    required this.label,
    required this.path,
    required this.onPicked,
    this.camera = false,
    this.optional = false,
  });

  final String label;
  final String? path;
  final ValueChanged<String> onPicked;
  final bool camera; // true = open the camera (used for the live selfie)
  final bool optional;

  Future<void> _pick() async {
    final picked = await ImagePicker().pickImage(
      source: camera ? ImageSource.camera : ImageSource.gallery,
      preferredCameraDevice: CameraDevice.front,
      maxWidth: 1600,
      imageQuality: 85,
    );
    if (picked != null) onPicked(picked.path);
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return InkWell(
      onTap: _pick,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        height: 130,
        decoration: BoxDecoration(
          border: Border.all(color: scheme.outlineVariant),
          borderRadius: BorderRadius.circular(12),
        ),
        clipBehavior: Clip.antiAlias,
        child: path == null
            ? Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(camera ? Icons.photo_camera : Icons.add_photo_alternate_outlined, size: 32),
                  const SizedBox(height: 6),
                  Text(optional ? '$label (optional)' : label),
                ],
              )
            : Stack(
                fit: StackFit.expand,
                children: [
                  Image.file(File(path!), fit: BoxFit.cover),
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 0,
                    child: Container(
                      color: Colors.black54,
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Text('$label - tap to change',
                          textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 12)),
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}

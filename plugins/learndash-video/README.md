# LearnDash Video Plugin

## Overview
This plugin enhances LearnDash's video handling capabilities with advanced features like server-side device detection, mobile/desktop video switching, and responsive aspect ratios. It's specifically designed to work with LearnDash's course and lesson structure.

## Features

### Core Functionality
- **Server-Side Device Detection**: Automatically detects mobile vs desktop devices
- **Dual Video Support**: Load different videos for mobile and desktop
- **Responsive Design**: Automatic aspect ratio adjustment (16:9 for desktop, 9:16 for mobile)
- **Efficient Loading**: Only loads the appropriate video per device (no hidden sources)
- **Debug Mode**: Detailed HTML comments and console logging for troubleshooting

### Supported Video Formats
- **MP4** (`.mp4`) - `video/mp4`
- **WebM** (`.webm`) - `video/webm`
- **Ogg** (`.ogv`, `.ogg`) - `video/ogg`
- **QuickTime** (`.mov`) - `video/quicktime`
- **AVI** (`.avi`) - `video/x-msvideo`

## Installation

1. Upload the `learndash-video` folder to your WordPress `mu-plugins/plugins/` directory
2. Ensure your `mu-plugins/mu-loader.php` includes the following code:
   ```php
   // Load plugins from mu-plugins/plugins
   $mu_plugins = glob(WPMU_PLUGIN_DIR . '/plugins/*/plugin.php');
   foreach ($mu_plugins as $plugin) {
       require_once $plugin;
   }
   ```
3. The plugin will automatically activate (mu-plugins don't require activation)

## Configuration

### Video Setup
1. **Desktop Video**: Set in LearnDash Lesson Settings → Video URL
2. **Mobile Video**: Add to LearnDash Lesson Settings → Lesson Materials (as a direct URL)

### Debug Mode
Debug information is output as HTML comments. To view:
1. Right-click on page → View Page Source
2. Look for `<!-- DEBUG: ... -->` comments

## Troubleshooting

### Common Issues

#### 1. Video Not Loading
- **Check**: Verify video URLs are correct and accessible
- **Debug**: Look for `<!-- DEBUG: Loading Video: [URL] -->` in page source
- **Solution**: Ensure videos are properly uploaded and URLs are correct

#### 2. Wrong Aspect Ratio
- **Check**: Verify device detection is working
- **Debug**: Look for `<!-- DEBUG: Device Type: [DEVICE] -->`
- **Solution**: Clear browser cache and test with different devices

#### 3. Mobile/Desktop Detection Issues
- **Check**: Test with real devices, not just browser emulation
- **Debug**: Check `<!-- DEBUG: Device Type: ... -->` output
- **Solution**: Update the mobile detection regex if needed

### Debugging Steps
1. View page source and search for `DEBUG:`
2. Check browser console for JavaScript logs
3. Verify file permissions on video files
4. Test with different browsers/devices

## Customization

### Adding New MIME Types
To support additional video formats, update the `$mime_types` array in the plugin:

```php
$mime_types = [
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'ogv'  => 'video/ogg',
    'ogg'  => 'video/ogg',
    'mov'  => 'video/quicktime',
    'avi'  => 'video/x-msvideo',
    // Add new formats here
    'm4v'  => 'video/x-m4v',
    'mkv'  => 'video/x-matroska'
];
```

### Modifying Device Detection
To adjust how devices are detected, modify the mobile detection logic:

```php
$is_mobile = wp_is_mobile() || preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $user_agent);
```

## Best Practices

### Video Optimization
1. **Compress Videos**: Use tools like HandBrake or FFmpeg
2. **Use CDN**: For better performance
3. **Lazy Loading**: Consider implementing for course pages with multiple videos

### Performance
- The plugin is designed to be lightweight
- Only loads one video source per device
- Minimal JavaScript (debugging only)

## Future Enhancements

### Planned Features
- [ ] Support for HLS/DASH streaming
- [ ] Video preloading options
- [ ] Custom video player controls
- [ ] Video analytics integration

### Known Limitations
- Currently doesn't support video playlists
- Limited to one video per lesson
- No built-in video compression

## Support

For support, please contact your development team with the following information:
1. URL of the affected page
2. Device/browser being used
3. Any error messages from browser console
4. Screenshots if possible

## Version History

### 1.0.0 (2025-08-13)
- Initial release
- Server-side device detection
- Mobile/desktop video switching
- Responsive aspect ratios
- Debug mode

## License
This plugin is proprietary software. All rights reserved.

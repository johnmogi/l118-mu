# LearnDash Video Plugin - Developer Documentation

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Code Structure](#code-structure)
3. [Core Functions](#core-functions)
4. [Device Detection](#device-detection)
5. [Video Processing](#video-processing)
6. [Debugging](#debugging)
7. [Common Issues & Solutions](#common-issues--solutions)
8. [Performance Considerations](#performance-considerations)
9. [Future Enhancements](#future-enhancements)

## Architecture Overview

The plugin follows a simple procedural architecture with these key components:

1. **Main Plugin File** (`plugin.php`): Contains all the core functionality
2. **Hooks & Filters**: Integrates with WordPress and LearnDash
3. **Device Detection**: Server-side detection of mobile vs desktop
4. **Video Processing**: Handles different video formats and sources
5. **Output Generation**: Creates responsive video HTML

## Code Structure

```
learndash-video/
├── plugin.php          # Main plugin file
├── README.md          # User documentation
└── DEVELOPER.md       # This file
```

## Core Functions

### `inject_single_lesson_video()`
Main function that handles video injection for single lessons.

**Parameters**: None (uses WordPress globals)
**Returns**: Outputs video HTML directly

### `inject_course_videos($content)`
Handles video injection for course pages with multiple lessons.

**Parameters**:
- `$content` (string): The course content

**Returns**: Modified content with injected videos

## Device Detection

The plugin uses a two-tiered approach for device detection:

1. **Primary**: WordPress's `wp_is_mobile()`
2. **Fallback**: User-Agent string matching

```php
$is_mobile = wp_is_mobile() || preg_match(
    '/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', 
    $_SERVER['HTTP_USER_AGENT']
);
```

## Video Processing

### Supported MIME Types

| Extension | MIME Type         | Notes                     |
|-----------|-------------------|---------------------------|
| .mp4      | video/mp4         | Most compatible format    |
| .webm     | video/webm        | Good for web              |
| .ogv      | video/ogg         | Open format               |
| .mov      | video/quicktime   | QuickTime format          |
| .avi      | video/x-msvideo   | Legacy Windows format      |

### Adding New Formats

1. Add the extension and MIME type to the `$mime_types` array
2. Test with various browsers
3. Update documentation

## Debugging

### Debug Output

Debug information is output as HTML comments in this format:

```html
<!-- DEBUG: [Category]: [Message] -->
```

### Common Debug Scenarios

1. **Video Not Loading**
   - Check for `<!-- DEBUG: Loading Video: [URL] -->`
   - Verify file permissions and URL accessibility

2. **Device Detection Issues**
   - Look for `<!-- DEBUG: Device Type: [TYPE] -->`
   - Test with different devices and user agents

## Common Issues & Solutions

### Issue: Video Not Displaying
**Possible Causes**:
- Incorrect URL in LearnDash settings
- File permissions
- Missing MIME type

**Solution**:
1. Check debug output
2. Verify file exists and is accessible
3. Check browser console for errors

### Issue: Wrong Aspect Ratio
**Possible Causes**:
- Device detection failure
- CSS conflicts

**Solution**:
1. Check debug output for device type
2. Inspect element to see applied styles
3. Clear cache and test again

## Performance Considerations

### Optimization Tips

1. **Video Optimization**
   - Use H.264 codec for MP4
   - Keep video bitrate under 2Mbps for 720p
   - Consider using a CDN for better delivery

2. **Caching**
   - Implement browser caching for video files
   - Consider using a service like Cloudflare

3. **Lazy Loading**
   - For course pages with multiple videos, implement lazy loading
   - Only load videos when they come into viewport

## Future Enhancements

### High Priority
- [ ] Implement video preloading options
- [ ] Add support for video subtitles
- [ ] Add video analytics

### Medium Priority
- [ ] Support for video playlists
- [ ] Picture-in-picture mode
- [ ] Custom video player controls

### Low Priority
- [ ] Video compression tools
- [ ] Bulk video processing
- [ ] Integration with video hosting services

## Testing

### Test Cases

1. **Device Detection**
   - [ ] iPhone (mobile)
   - [ ] iPad (tablet)
   - [ ] Android phone
   - [ ] Android tablet
   - [ ] Desktop Chrome
   - [ ] Desktop Firefox
   - [ ] Desktop Safari
   - [ ] Desktop Edge

2. **Video Formats**
   - [ ] MP4
   - [ ] WebM
   - [ ] Ogg
   - [ ] MOV
   - [ ] AVI

## Version Control

### Branching Strategy
- `main` - Production-ready code
- `develop` - Development branch
- `feature/*` - Feature branches
- `hotfix/*` - Critical bug fixes

### Commit Message Format
```
[type](scope): description

[optional body]

[optional footer]
```

**Types**:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Adding tests
- `chore`: Maintenance tasks

## License & Credits

This is proprietary software. All rights reserved.

---
*Last Updated: 2025-08-13*

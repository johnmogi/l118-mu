# LearnDash Instructor Quiz Categories

A WordPress MU plugin that adds instructor quiz categories functionality to LearnDash quiz edit screens.

## Description

This plugin adds a meta box to the LearnDash quiz edit screen that displays all LearnDash Question Categories (`ld_question_category`) as checkboxes, allowing instructors to easily categorize their quizzes.

## Features

- ✅ LearnDash compatibility check
- ✅ Meta box on quiz edit screen
- ✅ Display all question categories as checkboxes
- ✅ Clean, accessible interface with hover effects
- ✅ RTL language support
- ✅ Debug mode for development
- ✅ Proper plugin structure with templates

## Installation

This is an MU (Must Use) plugin, so it's automatically active once placed in the correct directory.

## File Structure

```
ld-instructor-quiz-cats/
├── ld-instructor-quiz-cats.php          # Main plugin file
├── includes/
│   └── class-ld-instructor-quiz-categories.php  # Main plugin class
├── templates/
│   ├── meta-box-quiz-categories.php     # Meta box template
│   └── debug-info.php                   # Debug info template
└── README.md                            # This file
```

## Requirements

- WordPress 5.0+
- LearnDash LMS plugin
- PHP 7.4+

## Usage

1. Navigate to any LearnDash quiz edit page
2. Look for the "Instructor Quiz Categories" meta box in the sidebar
3. Select categories using the checkboxes
4. Currently display-only (save functionality to be added later)

## Development

- Enable `WP_DEBUG` to see debug information
- Plugin follows WordPress coding standards
- Proper text domain for translations: `ld-instructor-quiz-cats`

## Version

1.0.0 - Initial release

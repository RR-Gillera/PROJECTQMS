# SeQueueR Logo Standards

## Standardized Logo Specifications

All headers across the SeQueueR application now use consistent logo sizing, design, and styling.

### Logo Specifications

#### Image Properties
- **Size**: `h-12 w-12` (48px × 48px)
- **Shape**: `rounded-full` (circular)
- **Object Fit**: `object-cover`
- **Alt Text**: "University of Cebu Student Affairs circular seal"

#### Typography
- **Title**: `text-blue-900 font-bold text-xl -mb-1`
  - Color: Blue-900 (#1e3a8a)
  - Weight: Bold
  - Size: XL (1.25rem)
  - Margin: -0.25rem bottom (tight spacing)

- **Subtitle**: `text-gray-600 text-sm`
  - Color: Gray-600 (#4b5563)
  - Size: SM (0.875rem)

#### Layout
- **Container**: `flex items-center space-x-4`
- **Text Container**: `leading-tight`
- **Spacing**: 1rem between logo and text

### Implementation

#### HTML Structure
```html
<div class="flex items-center space-x-4">
    <img alt="University of Cebu Student Affairs circular seal" 
         class="h-12 w-12 rounded-full object-cover" 
         src="path/to/sao-nobg.png"/>
    <div class="leading-tight">
        <h1 class="text-blue-900 font-bold text-xl -mb-1">SeQueueR</h1>
        <p class="text-gray-600 text-sm">UC Student Affairs</p>
    </div>
</div>
```

#### PHP Component
Use the standardized component:
```php
<?php include 'Components/Logo.php'; ?>
```

### Files Updated

✅ **Admin Header** (`Frontend/Personnel/Admin/Header.php`)
- Already using correct specifications

✅ **Working Header** (`Frontend/Personnel/Working/Header.php`)
- Already using correct specifications

✅ **Student Header** (`Frontend/Student/Header.php`)
- Updated from `h-10 w-10` to `h-12 w-12`
- Updated from `text-lg` to `text-xl`
- Updated from `text-xs` to `text-sm`
- Updated from `text-center` to `text-left`
- Updated from `ml-3` to `ml-4`

✅ **Personnel Header** (`Frontend/Personnel/Header.php`)
- Updated from `h-10 w-10` to `h-12 w-12`
- Updated from `text-lg` to `text-xl`
- Updated from `text-xs` to `text-sm`
- Updated from `text-center` to `text-left`
- Updated from `ml-3` to `ml-4`

✅ **Login Header** (`Frontend/Personnel/LoginHeader.php`)
- Updated from `h-10 w-10` to `h-12 w-12`
- Updated from `text-lg` to `text-xl`
- Updated from `text-xs` to `text-sm`
- Updated from `text-center` to `text-left`
- Updated from `ml-3` to `ml-4`

### Benefits

- **Consistency**: All headers now have identical logo presentation
- **Professional**: Larger logo size (48px) provides better visibility
- **Readable**: Larger text sizes improve readability
- **Maintainable**: Standardized component for easy updates
- **Accessible**: Consistent alt text and proper contrast ratios

### Future Updates

When updating logo specifications, modify:
1. The `Logo.php` component file
2. This documentation file
3. All individual header files if not using the component

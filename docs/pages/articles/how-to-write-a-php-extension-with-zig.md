---
layout: modernphp:page
parent: index
title: How to Write a PHP Extension with Zig?
description: >
    What if I told you that a language extension can bring many memory-safe
    parallel programming features?
---

# How to Write a PHP Extension with Zig

Written by: 
[Mateusz Charytoniuk](https://www.linkedin.com/in/mateusz-charytoniuk)

https://youtube.com/watch?v=-27aZtc3VbY

When writing code in a scripting language, sometimes you need that extra bit 
of performance (or maybe an async feature from [Zig](https://ziglang.org/)). 
In that case, consider using it in your project.

## Why Zig?

Zig is a statically typed, memory-safe, and thread-safe programming language.

It has a great interop with C, which makes it a perfect candidate for writing 
extensions for PHP. You don't have to worry about writing your bindings;
you can call the functions from PHP internals straight up as they can also
use C.

## The Plan

We will still use a simple C facade to register Zig extension because Zig
cannot translate all the C macros from the PHP source code into Zig 
code. You usually have to unwind them manually, so to avoid that, we will move
as much of the extension initialization into C as possible.

1. Create a basic C extension wrapper
2. Fill in the gaps with Zig (statically linking functions)
3. Phpize the extension
4. Profit :)

We will create a basic "Hello from ZIG!" extension.

### Basic C Extension Structure

All of those functions and entry points are mandatory.

In the header file, we will register `myextension_functions`
as `extern` because they will be statically linked later.

```c file:hello.h
#ifndef MYEXTENSION_H
#define MYEXTENSION_H
#define PHP_MY_PHP_EXTENSION_VERSION "1.0.0"

#include "php.h"

extern zend_function_entry myextension_functions[];

#endif
```

As you can see, many C macros are Zig's weak (arguably) point.

I am internally conflicted about whether I should call that a "weak" point because
Zig, by design, decided not to support any macros and that type of 
metaprogramming - I think rightfully so. On the other hand, that approach
also makes it harder for Zig to interop with macro-heavy C libraries.

```c file:hello.c
#include "hello.h"

PHP_MINIT_FUNCTION(my_php_extension) {
    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(my_php_extension) {
    return SUCCESS;
}

PHP_RINIT_FUNCTION(my_php_extension) {
    return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(my_php_extension) {
    return SUCCESS;
}

PHP_MINFO_FUNCTION(my_php_extension) {
    php_info_print_table_start();
    php_info_print_table_header(2, "My PHP Extension", "enabled");
    php_info_print_table_end();
}

zend_module_entry my_php_extension_module_entry = {
    STANDARD_MODULE_HEADER,
    "my_php_extension",
    myextension_functions,
    PHP_MINIT(my_php_extension),
    PHP_MSHUTDOWN(my_php_extension),
    PHP_RINIT(my_php_extension),
    PHP_RSHUTDOWN(my_php_extension),
    PHP_MINFO(my_php_extension),
    PHP_MY_PHP_EXTENSION_VERSION,
    STANDARD_MODULE_PROPERTIES
};

ZEND_GET_MODULE(my_php_extension)
```

You need a similar C structure, no matter what extension you intend to write.

### Zig Part

That is the core of our extension. Here, we define our `hello_world` function
that will be exported into PHP alongside its arguments and return type.

As you can see, we can use C functions directly with Zig code, which is great.

We can follow [PHP internals](https://www.phpinternalsbook.com/) documentation 
here.

`myextension_functions` has to end with an empty terminator, hence the extra
entry.

That is the basic extension that prints "Hello from ZIG!" but you can
expand it with more complex features.

```zig file:hello.zig
const std = @import("std");
const php = @cImport({
    @cInclude("php.h");
});

export fn hello_world(execute_data: ?*php.zend_execute_data, return_value: ?*php.zval) void {
    _ = execute_data;
    _ = return_value;
    _ = php.php_printf("Hello from ZIG!\n");
}

const arg_info = [_]php.zend_internal_arg_info{
    .{
        .name = null,
        .type = .{
            .type_mask = php.MAY_BE_NULL,
            .ptr = null,
        },
    },
};

export const myextension_functions = [_]php.zend_function_entry{
    php.zend_function_entry{
        .fname = "hello_world",
        .handler = hello_world,
        .arg_info = &arg_info,
        .num_args = 0,
        .flags = 0,
    },
    php.zend_function_entry{
        .fname = null,
        .handler = null,
        .arg_info = null,
        .num_args = 0,
        .flags = 0,
    },
};
```

Here, we configure the Zig build system to build our extension as a static 
library.

I took the included paths from the `php-config --includes` command. If you feel
like it, you can invoke it in the build system, so you don't have to spell out
include paths explicitly.

`/usr/include/x86_64-linux-gnu` is a common include path for Linux 
distributions. It uses my system's include paths, so you might have to adjust
them.

```zig file:build.zig
const std = @import("std");

pub fn build(b: *std.Build) void {
    const optimize = b.standardOptimizeOption(.{});
    const target = b.standardTargetOptions(.{});

    const library = b.addStaticLibrary(.{
        .name = "my_php_extension",
        .root_source_file = .{
            .path = "hello.zig",
        },
        .target = target,
        .optimize = optimize,
    });

    library.addIncludePath(.{
        .path = "/usr/include/php/20220829"
    });
    library.addIncludePath(.{
        .path = "/usr/include/php/20220829/main"
    });
    library.addIncludePath(.{
        .path = "/usr/include/php/20220829/TSRM"
    });
    library.addIncludePath(.{
        .path = "/usr/include/php/20220829/Zend"
    });
    library.addIncludePath(.{
        .path = "/usr/include"
    });
    library.addIncludePath(.{
        .path = "/usr/include/x86_64-linux-gnu"
    });

    b.installArtifact(library);
}
```

With those two files in place, after running `zig build`, you should have
`zig-out/lib/libmy_php_extension.a` file compiled, a static library
that we will link later with the C part of the PHP extension.

### PHP Part

To start with, we need [Autoconf](https://www.gnu.org/software/autoconf/)
configuration file for our extension.

```m4 file:config.m4
PHP_ARG_ENABLE(my_php_extension, whether to enable My PHP Extension,
[ --enable-my-php_extension   Enable My PHP Extension support])

if test "$PHP_MY_PHP_EXTENSION" != "no"; then
    PHP_REQUIRE_CXX()
    PHP_ADD_LIBRARY(stdc++, 1, MY_PHP_EXTENSION_SHARED_LIBADD)
    PHP_SUBST(MY_PHP_EXTENSION_SHARED_LIBADD)
    PHP_ADD_LIBRARY_WITH_PATH(my_php_extension, ./zig-out/lib, MY_PHP_EXTENSION_SHARED_LIBADD)
    PHP_NEW_EXTENSION(my_php_extension, hello.c, $ext_shared)
fi
```

The important line is 

```m4
PHP_ADD_LIBRARY_WITH_PATH(my_php_extension, ./zig-out/lib, MY_PHP_EXTENSION_SHARED_LIBADD)
```

Which tells the PHP build system to link the `libmy_php_extension.a` file from
the Zig build.

## Putting it All Together

Now that we have all the pieces, we can compile our extension.

Run the following commands:

1. `zig build` - to build the Zig static library
2. `phpize` - to prepare the PHP extension build
3. `./configure` - to configure the extension
4. `make` - to compile the extension

After that, you should have a `modules/my_php_extension.so` file that you can
load into your PHP.

To test if it works, you can run:

```bash
php -d extension=./modules/my_php_extension.so -r 'echo hello_world();'
```

You should see `Hello from ZIG!`.

Congratulations! You have just written a PHP extension with Zig!

## Conclusion

Zig is a great choice for writing extensions that require high performance or 
async features and can enrich your PHP experience.

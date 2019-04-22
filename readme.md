Phase
=====
A simple replacement for PHP. Should (eventually) compile to clean, well formatted, useable PHP.

Syntax
------
Here's a quick example:

```phase
package Some::Example {

  class Foo {

    foo
    bar = 'bar'

    new(foo) {
      this.foo = foo
    }

    sayFoo() {
      return this.foo + this.bar
    }

  }

}
```

Operators
---------
Phase's operators are generally similar to PHP or any other c-based language.

The only one that might be a surprise is the string concatenation operator, which is `+++`. This is to get around the fact that PHP uses a `.` to join strings, and there isn't an easy way to overload the `+` operator. This is definitely a place for improvement, but I suggest using string interpolation wherever possible anyway.

Classes
-------
Phase's class system is basically identical to Php. The main difference is that all classes, interfaces and traits MUST start with an uppercase letter, and that (as a result) the `new` keyword is unused.

```phase
class Foo {
  
  // You can define types on fields -- however, for now this does nothing and
  // is really only for documentation.
  foo: String
  
  // `new` is the same as `__construct` in PHP.
  //
  // In the case of function and method arguments, a typehint
  // WILL be genenerated in PHP. Note that scalar types like
  // `String` and `Array` are uppercase in Phase.
  new(foo: String) {
    this.foo = foo
  }

}

// Note that we don't need to use `new` becasue all uppercase
// identifiers MUST be types in Phase, so we can be sure that
// `Foo` is not a function.
var foo = Foo('foo')
print(foo.foo) // => 'foo'
```

A common use of constructors is to just set a property in the class, and Phase has a simple way of doing that:

```phase

class Bar {

  private bar: String

  new(
    // If `foo` is not an existing property, Phase will create
    // a public property for you.
    this.foo: String,
    // If a property DOES exist, it will not be overwritten.
    this.bar: String
  ) {}

  getBar() {
    return this.bar
  }

}

var bar = Bar('foo', 'bar')
bar.foo // => 'foo'
bar.getBar() // => 'bar'

```

Lambdas
-------
Phase makes working with lambdas (anonymous functions) far easier than PHP. For one thing, you no longer need to manually capture closures -- Phase handles that for you. The syntax is also very terse, and allows for some interesting possibilities for DSLs (especially when combined with Pipes).

Let's start with an overly complex example. Note the `| item |` -- this is where we define a lambda's parameters.

```phase
var addFoo = { |item|
  return '${item} foo'
}
print(addFoo('bar')) // => 'bar foo'  
```

We only have one expression in this lambda, and we actually don't need the `return` expression. Instead, Phase will automatically return an expression from a Lambda (or any function!) if:

- There is no newline after the parameter list.
- There is only one expression in the Lambda body.

Thus:

```phase
var addFoo = { |item| '${item} foo' }
print(addFoo('bar')) // => 'bar foo'  
```

There's one more improvement we can make to our extremely useful function: we don't actually need `|item|`. If no parameter list is provided to a Lambda, the parameter `it` will automatically be provided.

Thus:

```phase
var addFoo = { '${it} foo' }
print(addFoo('bar')) // => 'bar foo'  
```

Let's move on to another example. Say we have a function like the following which accepts a callback as its last parameter:

```phase
// Note: the automatic return rules apply to funcitons too, so
// this is the same as `return cb(name)`
function foo(name, cb) { cb(name) }
```

We could use it like this:

```phase
foo('bar', { '${it} foo' }) // => 'bar foo'
```

... but there's another way! We can use `trailing lambdas`:

```phase
foo('bar') { '${it} foo' } // => 'bar foo'
```

This simply means that a lambda that comes after an identifier or call expression will be applied as the last argument.

You don't even need to use parens if there is only one argument in the target method:

```
function foo(cb) {
  return cb('foo');
}
foo { '${it} bar' } // => 'foo bar'
```

Pipes
-----
The `Pipe` operator (`|>`) takes the result of one expression and pipes it into the _last_ argument of a following function, method, or lambda. For example:

```
"FOO" |> strtolower() // => 'foo'
```

> An important note: you MUST include parens. `"FOO" |> strtolower` will not work.

This isn't much more helpful than doing `strtolower("FOO")`, but consider:

```
"FOO.BAR"
  |> explode('.')
  |> array_map { it |> stringtolower() }
  |> implode('+') // => 'foo+bar'
```

You can also pipe expressions directly into Lambdas, which is useful if you need to use a different argument position:

```
var example = { |a, b| a +++ b }

"FOO.BAR"
  |> explode('.')
  |> implode('+')
  |> { example(it, ".foo") }  // => 'foo+bar.foo'
```

(This is likely a place for optimization and inlining)

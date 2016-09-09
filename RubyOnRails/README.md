# RestPki

TODO: Write a description

## Installation

Make a copy of repository on your computer

	$ git clone git@github.com:LacunaSoftware/RestPkiSamples.git

Go to Rails repository

	$ cd RubyOnRails

Install the dependencies of the project using Bundler

	$ bundle install

Create the databases for the application

	$ rake db:create

Update the database definitions to their latest version

	$ rake db:migrate

And then execute:

    $ rails server

## Usage

Modify a initialize file rest_pki.rb   

```ruby
    # config/initializers/rest_pki.rb
    require 'rest_pki'
    RestPki.api_key = 'PLACE_YOUR_API_KEY_HERE'
```

## Contributing



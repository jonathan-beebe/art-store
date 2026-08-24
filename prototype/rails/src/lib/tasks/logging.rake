# The schema and seed commands tell the same story a request does, so a run
# from the command line reads back the same way.

# Each migration announces itself as it is applied, inside the run carrying
# them all.
module TellsItsStory
  def migrate(direction)
    Story.tell("migrate.apply", "applying #{name}",
      version: version.to_s, name: name, direction: direction.to_s) do |story|
      applied = super

      story.did("applied #{name}", version: version.to_s, name: name, direction: direction.to_s)

      applied
    end
  end
end

migrating = nil
seeding = nil

task telling_migrations: :environment do
  Current.actor_type = "system"
  ActiveRecord::Migration.prepend(TellsItsStory)
  migrating = Story.start("migrate.run", "applying pending migrations", task: "db:migrate")
end

task telling_seeds: :environment do
  Current.actor_type = "system"
  seeding = Story.start("seed.run", "seeding the database", task: "db:seed")
end

# `execute` runs a task's own actions, after its prerequisites
# (`telling_migrations`/`telling_seeds`, which open the story) have already
# run. Wrapping it here brackets exactly the work each story is about: a
# `did` line when the actions run to completion, a `failed` line — with the
# raised error re-raised — when a migration or the seed file doesn't.
Rake::Task["db:migrate"].enhance([ :telling_migrations ])
Rake::Task["db:migrate"].singleton_class.prepend(Module.new do
  define_method(:execute) do |*args|
    result = super(*args)
    Current.actor_type = "system"
    migrating.did("applied pending migrations", task: "db:migrate")
    result
  rescue StandardError => e
    Current.actor_type = "system"
    migrating.failed(e)
    raise
  ensure
    migrating.close
  end
end)

Rake::Task["db:seed"].enhance([ :telling_seeds ])
Rake::Task["db:seed"].singleton_class.prepend(Module.new do
  define_method(:execute) do |*args|
    result = super(*args)
    Current.actor_type = "system"
    seeding.did("seeded the database", task: "db:seed")
    result
  rescue StandardError => e
    Current.actor_type = "system"
    seeding.failed(e)
    raise
  ensure
    seeding.close
  end
end)

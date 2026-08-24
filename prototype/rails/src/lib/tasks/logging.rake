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

Rake::Task["db:migrate"].enhance([ :telling_migrations ]) do
  Current.actor_type = "system"
  migrating.did("applied pending migrations", task: "db:migrate")
  migrating.close
end

Rake::Task["db:seed"].enhance([ :telling_seeds ]) do
  Current.actor_type = "system"
  seeding.did("seeded the database", task: "db:seed")
  seeding.close
end

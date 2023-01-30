# Zero Version Rules

`loco` follows SemVer, but it is currently within the `0.y` period - which is [defined](https://semver.org/) as follows:

> Major version zero (0.y.z) is for initial development. Anything MAY change at any time. The public API SHOULD NOT be considered stable.

So that doesn't mean much! But it's understandable - because, well, things do change during initial development. And there's less patience
for formality during that period.

But `loco` has been in `0.y` for some time, and there's no specific benchmark for when to change to `1.0.0`.

So let's fill-in the `0.y` rules a bit more:

* If there is a major enhancement or breaking change, then increment to `0.y`.
* Within `0.y`, there is a period of "development" followed by "maintenance".
* For the first few days, `0.y` is in "development". Each `0.y.z` can do major or breaking changes.
* After the first few days, `0.y` implicitly switches to "maintenance". Each `0.y.z` may only include minor fixes.

For upstream, this process doesn't require any special flag or action to mark the "maintenance" period -- it's
implicit.  For downstream, it means that most of the life of `0.y` is actually "maintenance" period. (Pinning
`~0.y.z` should be similar to pinning `~MAJOR.MINOR.PATCH`.) However, if you're strictly looking for "maintenance"
period, then you should wait until the `0.y` is a few days old.

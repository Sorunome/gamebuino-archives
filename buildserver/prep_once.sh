#!/bin/bash --

for d in /sys/fs/cgroup/*; do
         f=$(basename $d)
         echo "looking at $f"
         if [ "$f" = "cpuset" ]; then
                 echo 1 | sudo tee -a $d/cgroup.clone_children;
         elif [ "$f" = "memory" ]; then
                 echo 1 | sudo tee -a $d/memory.use_hierarchy;
         fi
         sudo mkdir -p $d/$USER
         sudo chown -R $USER $d/$USER
done


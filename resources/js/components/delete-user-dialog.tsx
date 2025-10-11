// @/components/admin/users/delete-user-dialog.tsx
import React from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { DropdownMenuItem } from "@/components/ui/dropdown-menu";
import { useForm } from "@inertiajs/react";

interface DeleteUserDialogProps {
  user: {
    id: number;
    name: string;
  };
}

const DeleteUserDialog: React.FC<DeleteUserDialogProps> = ({ user }) => {
  const { delete: destroy, processing } = useForm();

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    destroy(route("admin.users.delete", { user: user.id }));
  };

  return (
    <Dialog>
      <DialogTrigger asChild>
        <DropdownMenuItem onSelect={(e) => e.preventDefault()}>
          Delete User
        </DropdownMenuItem>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Are you sure?</DialogTitle>
          <DialogDescription>
            This will permanently delete the user <strong>{user.name}</strong>. This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={submit}>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => {}}>
              Cancel
            </Button>
            <Button type="submit" variant="destructive" disabled={processing}>
              Delete
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
};

export default DeleteUserDialog;
